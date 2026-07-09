<?php

namespace App\Support;

use App\Models\AlisBackupConfiguration;
use App\Models\User;
use Illuminate\Support\Facades\Process;
use RuntimeException;

class AlisBackupProvisioningService
{
    public function __construct(
        private readonly AlisRemoteBackupDeploymentService $deploymentService,
    ) {
    }

    public function provision(AlisBackupConfiguration $configuration, User $user): AlisBackupConfiguration
    {
        $configuration->forceFill([
            'status' => AlisBackupConfiguration::STATUS_PENDING_PROVISIONING,
            'last_provisioning_error' => null,
        ])->save();

        try {
            $this->ensureProvisioningConfigured();
            $this->provisionRemoteDirectory($configuration);

            $deployment = $this->deploymentService->deploy($user);

            if ($deployment->status !== 'success') {
                throw new RuntimeException('The facility SSH key could not be authorized on the central backup server.');
            }

            $configuration->forceFill([
                'status' => AlisBackupConfiguration::STATUS_PROVISIONED,
                'provisioned_at' => now(),
                'last_provisioning_error' => null,
            ])->save();
        } catch (\Throwable $exception) {
            $configuration->forceFill([
                'status' => AlisBackupConfiguration::STATUS_PROVISIONING_FAILED,
                'last_provisioning_error' => $this->safeMessage($exception),
            ])->save();
        }

        return $configuration->fresh();
    }

    private function ensureProvisioningConfigured(): void
    {
        foreach (['server', 'user', 'root', 'ssh_key'] as $key) {
            if (blank(config("alis_backup.{$key}"))) {
                throw new RuntimeException('The central A-LIS backup server is not fully configured.');
            }
        }

        if ((int) config('alis_backup.port', 0) <= 0) {
            throw new RuntimeException('The central A-LIS backup server SSH port is invalid.');
        }

        $identityFile = (string) config('alis_backup.ssh_key');

        if (! is_file($identityFile)) {
            throw new RuntimeException('The central A-LIS backup deployment SSH key could not be found.');
        }
    }

    private function provisionRemoteDirectory(AlisBackupConfiguration $configuration): void
    {
        $remote = config('alis_backup.user').'@'.config('alis_backup.server');
        $port = (string) config('alis_backup.port', 22);
        $identityFile = (string) config('alis_backup.ssh_key');
        $root = rtrim((string) config('alis_backup.root'), '/');
        $directory = $root.'/'.$configuration->backup_directory_name;
        $user = (string) config('alis_backup.user');

        $command = sprintf(
            'sudo install -d -o %s -g %s -m 750 %s',
            $this->shellEscape($user),
            $this->shellEscape($user),
            $this->shellEscape($directory)
        );

        $result = Process::timeout(30)->run([
            'ssh',
            '-p',
            $port,
            '-o',
            'BatchMode=yes',
            '-i',
            $identityFile,
            $remote,
            $command,
        ]);

        if ($result->failed()) {
            throw new RuntimeException('The facility backup directory could not be provisioned on the central backup server.');
        }
    }

    private function safeMessage(\Throwable $exception): string
    {
        return match (true) {
            $exception instanceof RuntimeException => $exception->getMessage(),
            default => 'Provisioning failed on the central backup server.',
        };
    }

    private function shellEscape(string $value): string
    {
        return "'".str_replace("'", "'\"'\"'", $value)."'";
    }
}
