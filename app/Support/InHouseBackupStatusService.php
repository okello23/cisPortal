<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Process;

class InHouseBackupStatusService
{
    /** @return array<int, array{filename: string, backed_up_at: CarbonImmutable, size_bytes: int}>|null */
    public function recentBackups(string $directoryName, int $limit = 10): ?array
    {
        if (preg_match('/\A[a-zA-Z0-9_-]+\z/', $directoryName) !== 1 || ! $this->configured()) return null;
        $directory = $this->directoryPath($directoryName);
        $command = 'find '.$this->escape($directory).' -maxdepth 1 -type f -name '.$this->escape('*.sql.gz').' -printf '.$this->escape("%T@\t%s\t%f\n");
        $result = Process::timeout(15)->run($this->sshCommand($command));
        if ($result->failed()) return null;

        return collect(preg_split('/\R/', trim($result->output())) ?: [])
            ->map(function (string $line) {
                $parts = explode("\t", $line, 3);
                if (count($parts) !== 3 || ! is_numeric($parts[0]) || ! ctype_digit($parts[1])) return null;
                return ['filename' => trim($parts[2]), 'backed_up_at' => CarbonImmutable::createFromTimestamp((int) floor((float) $parts[0])), 'size_bytes' => (int) $parts[1]];
            })->filter()->sortByDesc(fn (array $backup) => $backup['backed_up_at']->timestamp)->take($limit)->values()->all();
    }

    public function directoryPath(string $directoryName): string
    {
        $root = rtrim((string) config('alis_backup.in_house_root', '/backup'), '/');
        return $root.'/inhouse_systems/'.$directoryName;
    }

    private function configured(): bool
    {
        return filled(config('alis_backup.server')) 
            && filled(config('alis_backup.user')) 
            && filled(config('alis_backup.in_house_root', '/backup')) 
            && is_file($this->resolveSshKey());
    }

    private function sshCommand(string $command): array
    {
        return ['ssh', '-p', (string) config('alis_backup.port', 22), '-o', 'BatchMode=yes', '-i', $this->resolveSshKey(), config('alis_backup.user').'@'.config('alis_backup.server'), $command];
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
