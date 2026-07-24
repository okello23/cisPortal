<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class AlisBackupStatusService
{
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

        $server = (string) config('alis_backup.server');
        $user = (string) config('alis_backup.user');
        $port = (string) config('alis_backup.port', 22);
        $identityFile = (string) config('alis_backup.ssh_key');
        $root = rtrim((string) config('alis_backup.root'), '/');

        if ($server === '' || $user === '' || $root === '' || ! is_file($identityFile)) {
            return null;
        }

        $command = sprintf(
            'find %s -mindepth 2 -maxdepth 2 -type f -name %s -printf %s',
            $this->shellEscape($root),
            $this->shellEscape('*.sql.gz'),
            $this->shellEscape("%T@\t%h\n"),
        );

        try {
            $result = Process::timeout(15)->run([
                'ssh',
                '-p',
                $port,
                '-o',
                'BatchMode=yes',
                '-i',
                $identityFile,
                $user.'@'.$server,
                $command,
            ]);

            if ($result->failed()) {
                Log::warning('Unable to check A-LIS backup timestamps.', [
                    'backup_server' => $server,
                    'exit_code' => $result->exitCode(),
                    'stderr' => mb_substr(trim($result->errorOutput()), 0, 1000),
                ]);

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
                'backup_server' => $server,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    private function shellEscape(string $value): string
    {
        return "'".str_replace("'", "'\"'\"'", $value)."'";
    }
}
