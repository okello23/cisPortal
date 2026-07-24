<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use Symfony\Component\Process\Process as SymfonyProcess;

class AlisBackupStatusService
{
    /**
     * @return array<int, array{filename: string, backed_up_at: CarbonImmutable}>|null
     */
    public function recentBackups(string $directoryName, int $limit = 3): ?array
    {
        if (! $this->isSafePathSegment($directoryName)) {
            return null;
        }

        $connection = $this->connection();

        if ($connection === null) {
            return null;
        }

        $directory = $connection['root'].'/'.$directoryName;
        $command = sprintf(
            'find %s -maxdepth 1 -type f -name %s -printf %s',
            $this->shellEscape($directory),
            $this->shellEscape('*.sql.gz'),
            $this->shellEscape("%T@\t%f\n"),
        );

        try {
            $result = Process::timeout(15)->run($this->sshCommand($connection, $command));

            if ($result->failed()) {
                $this->logFailure($connection['server'], $result->exitCode(), $result->errorOutput());

                return null;
            }

            return collect(preg_split('/\R/', trim($result->output())) ?: [])
                ->map(function (string $line) {
                    if ($line === '' || ! str_contains($line, "\t")) {
                        return null;
                    }

                    [$timestamp, $filename] = explode("\t", $line, 2);
                    $filename = trim($filename);

                    if (! is_numeric($timestamp) || ! $this->isBackupFilename($filename)) {
                        return null;
                    }

                    return [
                        'filename' => $filename,
                        'backed_up_at' => CarbonImmutable::createFromTimestamp((int) floor((float) $timestamp)),
                    ];
                })
                ->filter()
                ->sortByDesc(fn (array $backup) => $backup['backed_up_at']->timestamp)
                ->take(max(1, $limit))
                ->values()
                ->all();
        } catch (\Throwable $exception) {
            Log::warning('Unable to list recent A-LIS backups.', [
                'backup_server' => $connection['server'],
                'directory' => $directoryName,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    public function assertDownloadable(string $directoryName, string $filename): void
    {
        if (! $this->isSafePathSegment($directoryName) || ! $this->isBackupFilename($filename)) {
            throw new RuntimeException('Invalid backup file.');
        }

        $connection = $this->connection();

        if ($connection === null) {
            throw new RuntimeException('The central backup server is unavailable.');
        }

        $result = Process::timeout(15)->run(
            $this->sshCommand(
                $connection,
                'test -f '.$this->shellEscape($connection['root'].'/'.$directoryName.'/'.$filename)
            )
        );

        if ($result->failed()) {
            throw new RuntimeException('The requested backup file was not found.');
        }
    }

    public function streamBackup(string $directoryName, string $filename): void
    {
        $connection = $this->connection();

        if ($connection === null || ! $this->isSafePathSegment($directoryName) || ! $this->isBackupFilename($filename)) {
            throw new RuntimeException('The requested backup file cannot be downloaded.');
        }

        $process = new SymfonyProcess($this->sshCommand(
            $connection,
            'cat -- '.$this->shellEscape($connection['root'].'/'.$directoryName.'/'.$filename)
        ));
        $process->setTimeout(null);
        $process->run(function (string $type, string $buffer): void {
            if ($type === SymfonyProcess::OUT) {
                echo $buffer;
                flush();
            }
        });

        if (! $process->isSuccessful()) {
            Log::error('A-LIS backup download stream failed.', [
                'backup_server' => $connection['server'],
                'directory' => $directoryName,
                'filename' => $filename,
                'exit_code' => $process->getExitCode(),
            ]);
        }
    }

    /**
     * @param  Collection<int, string>  $directoryNames
     * @return array<string, CarbonImmutable|null>|null
     */
    public function latestBackups(Collection $directoryNames): ?array
    {
        $directoryNames = $directoryNames->filter()->unique()->values();

        if ($directoryNames->isEmpty()) {
            return [];
        }

        $connection = $this->connection();

        if ($connection === null) {
            return null;
        }

        $command = sprintf(
            'find %s -mindepth 2 -maxdepth 2 -type f -name %s -printf %s',
            $this->shellEscape($connection['root']),
            $this->shellEscape('*.sql.gz'),
            $this->shellEscape("%T@\t%h\n"),
        );

        try {
            $result = Process::timeout(15)->run($this->sshCommand($connection, $command));

            if ($result->failed()) {
                $this->logFailure($connection['server'], $result->exitCode(), $result->errorOutput());

                return null;
            }

            $latestBackups = array_fill_keys($directoryNames->all(), null);

            foreach (preg_split('/\R/', trim($result->output())) ?: [] as $line) {
                if ($line === '' || ! str_contains($line, "\t")) {
                    continue;
                }

                [$timestamp, $directory] = explode("\t", $line, 2);
                $directoryName = basename(trim($directory));

                if (! array_key_exists($directoryName, $latestBackups) || ! is_numeric($timestamp)) {
                    continue;
                }

                $backedUpAt = CarbonImmutable::createFromTimestamp((int) floor((float) $timestamp));

                if ($latestBackups[$directoryName] === null || $backedUpAt->isAfter($latestBackups[$directoryName])) {
                    $latestBackups[$directoryName] = $backedUpAt;
                }
            }

            return $latestBackups;
        } catch (\Throwable $exception) {
            Log::warning('Unable to check A-LIS backup timestamps.', [
                'backup_server' => $connection['server'],
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    private function shellEscape(string $value): string
    {
        return "'".str_replace("'", "'\"'\"'", $value)."'";
    }

    /**
     * @return array{server: string, user: string, port: string, identity_file: string, root: string}|null
     */
    private function connection(): ?array
    {
        $connection = [
            'server' => (string) config('alis_backup.server'),
            'user' => (string) config('alis_backup.user'),
            'port' => (string) config('alis_backup.port', 22),
            'identity_file' => (string) config('alis_backup.ssh_key'),
            'root' => rtrim((string) config('alis_backup.root'), '/'),
        ];

        if ($connection['server'] === '' || $connection['user'] === '' || $connection['root'] === '' || ! is_file($connection['identity_file'])) {
            return null;
        }

        return $connection;
    }

    /**
     * @param  array{server: string, user: string, port: string, identity_file: string, root: string}  $connection
     * @return array<int, string>
     */
    private function sshCommand(array $connection, string $command): array
    {
        return [
            'ssh',
            '-p',
            $connection['port'],
            '-o',
            'BatchMode=yes',
            '-i',
            $connection['identity_file'],
            $connection['user'].'@'.$connection['server'],
            $command,
        ];
    }

    private function isSafePathSegment(string $value): bool
    {
        return preg_match('/\A[a-zA-Z0-9_-]+\z/', $value) === 1;
    }

    private function isBackupFilename(string $filename): bool
    {
        return basename($filename) === $filename
            && preg_match('/\A[a-zA-Z0-9_.-]+\.sql\.gz\z/', $filename) === 1;
    }

    private function logFailure(string $server, int $exitCode, string $errorOutput): void
    {
        Log::warning('Unable to check A-LIS backup timestamps.', [
            'backup_server' => $server,
            'exit_code' => $exitCode,
            'stderr' => mb_substr(trim($errorOutput), 0, 1000),
        ]);
    }
}
