<?php

namespace App\Support;

use App\Models\InHouseBackupConfiguration;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use RuntimeException;

class InHouseBackupProvisioningService
{
    public function provision(InHouseBackupConfiguration $configuration): InHouseBackupConfiguration
    {
        try {
            $this->ensureConfigured();
            $this->createRemoteDirectory($configuration);
            $configuration->update(['status' => 'active', 'provisioned_at' => now(), 'last_provisioning_error' => null]);
        } catch (\Throwable $exception) {
            Log::error('In-house backup provisioning failed.', ['configuration_id' => $configuration->id, 'backup_directory_name' => $configuration->backup_directory_name, 'error' => $exception->getMessage()]);
            $configuration->update(['status' => 'provisioning_failed', 'last_provisioning_error' => $exception instanceof RuntimeException ? $exception->getMessage() : 'Provisioning failed on the central backup server.']);
        }

        return $configuration->fresh();
    }

    private function ensureConfigured(): void
    {
        $server = config('alis_backup.in_house_server') ?: config('alis_backup.server');
        $user = config('alis_backup.in_house_user') ?: config('alis_backup.user');
        $sshKey = config('alis_backup.ssh_key');

        if (blank($server) || blank($user) || blank($sshKey)) {
            throw new RuntimeException('The central backup server is not fully configured.');
        }
        if (! is_file($this->resolveSshKey())) throw new RuntimeException('The central backup deployment SSH key could not be found.');
    }

    private function createRemoteDirectory(InHouseBackupConfiguration $configuration): void
    {
        $server = (string) (config('alis_backup.in_house_server') ?: config('alis_backup.server'));
        $port = (string) (config('alis_backup.in_house_port') ?: config('alis_backup.port', 22));
        $user = (string) (config('alis_backup.in_house_user') ?: config('alis_backup.user'));
        $root = rtrim((string) config('alis_backup.in_house_root', '/backup'), '/');
        $parentDirectory = $root.'/inhouse_systems';
        $directory = $configuration->backup_directory_path;
        $command = sprintf('sudo install -d -o %s -g %s -m 750 %s %s', $this->escape($user), $this->escape($user), $this->escape($parentDirectory), $this->escape($directory));
        $result = Process::timeout(30)->run(['ssh', '-p', $port, '-o', 'BatchMode=yes', '-i', $this->resolveSshKey(), $user.'@'.$server, $command]);
        if ($result->failed()) throw new RuntimeException('The in-house backup directory could not be provisioned on the central backup server.');
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
