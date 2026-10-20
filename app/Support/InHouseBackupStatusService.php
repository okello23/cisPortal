<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Process;

class InHouseBackupStatusService
{
    /** 
     * @return array{
     *     status: 'ok'|'permission_denied'|'unavailable'|'empty',
     *     message: string|null,
     *     backups: array<int, array{filename: string, backed_up_at: CarbonImmutable, size_bytes: int}>
     * }
     */
    public function checkStatus(string $directoryName, int $limit = 10): array
    {
        if (preg_match('/\A[a-zA-Z0-9_-]+\z/', $directoryName) !== 1) {
            return [
                'status' => 'unavailable',
                'message' => 'Invalid directory name.',
                'backups' => [],
            ];
        }

        $key = $this->resolveSshKey();
        if ($key !== '' && file_exists($key) && ! is_readable($key)) {
            return [
                'status' => 'permission_denied',
                'message' => 'Permission denied: Local deployment SSH key is not readable ('.$key.').',
                'backups' => [],
            ];
        }

        if (! $this->configured()) {
            return [
                'status' => 'unavailable',
                'message' => 'The central backup server is not fully configured.',
                'backups' => [],
            ];
        }

        $directory = $this->directoryPath($directoryName);
        $command = 'find '.$this->escape($directory).' -maxdepth 1 -type f -name '.$this->escape('*.sql.gz').' -printf '.$this->escape("%T@\t%s\t%f\n");
        $result = Process::timeout(15)->run($this->sshCommand($command));

        $output = $result->output();
        $errorOutput = $result->errorOutput();
        $combined = $errorOutput.' '.$output;

        if (stripos($combined, 'permission denied') !== false) {
            $cleaned = trim($errorOutput) ?: trim($output);
            return [
                'status' => 'permission_denied',
                'message' => $cleaned ?: 'Permission denied when accessing '.$directory.' on the central backup server.',
                'backups' => [],
            ];
        }

        if ($result->failed()) {
            return [
                'status' => 'unavailable',
                'message' => trim($errorOutput) ?: 'Failed to inspect backups on the central backup server.',
                'backups' => [],
            ];
        }

        $backups = collect(preg_split('/\R/', trim($output)) ?: [])
            ->map(function (string $line) {
                $parts = explode("\t", $line, 3);
                if (count($parts) !== 3 || ! is_numeric($parts[0]) || ! ctype_digit($parts[1])) return null;
                return ['filename' => trim($parts[2]), 'backed_up_at' => CarbonImmutable::createFromTimestamp((int) floor((float) $parts[0])), 'size_bytes' => (int) $parts[1]];
            })->filter()->sortByDesc(fn (array $backup) => $backup['backed_up_at']->timestamp)->take($limit)->values()->all();

        return [
            'status' => $backups !== [] ? 'ok' : 'empty',
            'message' => null,
            'backups' => $backups,
        ];
    }

    /** @return array<int, array{filename: string, backed_up_at: CarbonImmutable, size_bytes: int}>|null */
    public function recentBackups(string $directoryName, int $limit = 10): ?array
    {
        $status = $this->checkStatus($directoryName, $limit);

        if ($status['status'] === 'ok') {
            return $status['backups'];
        }

        if ($status['status'] === 'empty') {
            return [];
        }

        return null;
    }

    public function directoryPath(string $directoryName): string
    {
        $root = rtrim((string) config('alis_backup.in_house_root', '/backup'), '/');
        return $root.'/inhouse_systems/'.$directoryName;
    }

    private function configured(): bool
    {
        $server = config('alis_backup.in_house_server') ?: config('alis_backup.server');
        $user = config('alis_backup.in_house_user') ?: config('alis_backup.user');

        return filled($server) 
            && filled($user) 
            && filled(config('alis_backup.in_house_root', '/backup')) 
            && is_file($this->resolveSshKey());
    }

    private function sshCommand(string $command): array
    {
        $server = (string) (config('alis_backup.in_house_server') ?: config('alis_backup.server'));
        $port = (string) (config('alis_backup.in_house_port') ?: config('alis_backup.port', 22));
        $user = (string) (config('alis_backup.in_house_user') ?: config('alis_backup.user'));

        return ['ssh', '-p', $port, '-o', 'BatchMode=yes', '-i', $this->resolveSshKey(), $user.'@'.$server, $command];
    }

    private function resolveSshKey(): string
    {
        $key = (string) config('alis_backup.ssh_key');
        if (str_starts_with($key, '~/')) {
            $home = getenv('HOME') ?: ($_SERVER['HOME'] ?? '');
            if ($home) {
                return $home.substr($key, 1);
            }
        }
        return $key;
    }

    private function escape(string $value): string
    {
        return "'".str_replace("'", "'\"'\"'", $value)."'";
    }
}
