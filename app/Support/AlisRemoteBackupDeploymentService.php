<?php

namespace App\Support;

use App\Models\AlisRemoteBackupDeployment;
use App\Models\AlisRemoteBackupKey;
use App\Models\AlisRemoteBackupKeyHistory;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use RuntimeException;

class AlisRemoteBackupDeploymentService
{
    public function deploy(User $user): AlisRemoteBackupDeployment
    {
        $activeKeys = AlisRemoteBackupKey::query()
            ->with('facility.region')
            ->where('status', 'active')
            ->orderBy('facility_id')
            ->get();

        $pendingKeys = $activeKeys->where('deployment_status', 'pending')->values();
        $deployment = AlisRemoteBackupDeployment::query()->create([
            'deployment_batch_reference' => 'ALIS-DEPLOY-'.now()->format('YmdHis').'-'.Str::upper(Str::random(6)),
            'deployed_by' => $user->id,
            'deployed_at' => now(),
            'status' => 'failed',
            'total_active_keys' => $activeKeys->count(),
            'total_pending_changes' => $pendingKeys->count(),
            'backup_server' => (string) config('alis_remote_backup_keys.host'),
            'authorized_keys_path' => (string) config('alis_remote_backup_keys.authorized_keys_path'),
        ]);

        try {
            $this->ensureDeploymentConfigured();
            $this->pushAuthorizedKeys($activeKeys);

            DB::transaction(function () use ($pendingKeys, $deployment, $user) {
                foreach ($pendingKeys as $key) {
                    $key->forceFill([
                        'deployment_status' => 'deployed',
                        'deployed_by' => $user->id,
                        'deployed_at' => $deployment->deployed_at,
                        'last_deployment_error' => null,
                    ])->save();

                    AlisRemoteBackupKeyHistory::query()->create([
                        'facility_id' => $key->facility_id,
                        'action' => 'deployed',
                        'old_public_key' => $key->public_key,
                        'new_public_key' => $key->public_key,
                        'old_fingerprint' => $key->fingerprint,
                        'new_fingerprint' => $key->fingerprint,
                        'performed_by' => $user->id,
                        'performed_at' => now(),
                        'deployment_batch_id' => $deployment->id,
                    ]);
                }

                $deployment->forceFill([
                    'status' => 'success',
                    'error_message' => null,
                ])->save();
            });
        } catch (\Throwable $exception) {
            DB::transaction(function () use ($pendingKeys, $deployment, $user, $exception) {
                foreach ($pendingKeys as $key) {
                    $key->forceFill([
                        'deployment_status' => 'failed',
                        'last_deployment_error' => $exception->getMessage(),
                    ])->save();

                    AlisRemoteBackupKeyHistory::query()->create([
                        'facility_id' => $key->facility_id,
                        'action' => 'deployment_failed',
                        'old_public_key' => $key->public_key,
                        'new_public_key' => $key->public_key,
                        'old_fingerprint' => $key->fingerprint,
                        'new_fingerprint' => $key->fingerprint,
                        'performed_by' => $user->id,
                        'performed_at' => now(),
                        'deployment_batch_id' => $deployment->id,
                        'comments' => $exception->getMessage(),
                    ]);
                }

                $deployment->forceFill([
                    'status' => $pendingKeys->isEmpty() ? 'failed' : 'partial',
                    'error_message' => $exception->getMessage(),
                ])->save();
            });
        }

        return $deployment->fresh();
    }

    public function renderAuthorizedKeys(Collection $keys): string
    {
        return $keys
            ->map(function (AlisRemoteBackupKey $key) {
                $parts = preg_split('/\s+/', trim($key->public_key), 3) ?: [];
                $identity = $key->facility->code
                    ? Str::upper($key->facility->code)
                    : Str::upper(Str::slug($key->facility->name, '-'));

                return trim(($parts[0] ?? $key->key_type).' '.($parts[1] ?? '').' alis_backup:'.$identity);
            })
            ->filter()
            ->implode(PHP_EOL).PHP_EOL;
    }

    private function ensureDeploymentConfigured(): void
    {
        foreach (['host', 'user', 'authorized_keys_path', 'install_command', 'remote_temp_path'] as $key) {
            if (blank(config("alis_remote_backup_keys.{$key}"))) {
                throw new RuntimeException("A-LIS remote backup deployment is missing configuration for {$key}.");
            }
        }

        if ((int) config('alis_remote_backup_keys.port', 0) <= 0) {
            throw new RuntimeException('A-LIS remote backup deployment is missing a valid SSH port.');
        }

        $identityFile = config('alis_remote_backup_keys.identity_file');

        if (filled($identityFile) && ! is_file((string) $identityFile)) {
            throw new RuntimeException('A-LIS remote backup deployment SSH identity file was not found.');
        }
    }

    private function pushAuthorizedKeys(Collection $activeKeys): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'alis_keys_');

        if ($tempFile === false) {
            throw new RuntimeException('Unable to create a temporary authorized_keys file.');
        }

        file_put_contents($tempFile, $this->renderAuthorizedKeys($activeKeys));

        $remote = config('alis_remote_backup_keys.user').'@'.config('alis_remote_backup_keys.host');
        $remoteTempPath = (string) config('alis_remote_backup_keys.remote_temp_path');
        $port = (string) config('alis_remote_backup_keys.port', 22);
        $identityFile = config('alis_remote_backup_keys.identity_file');
        $sshOptions = $this->buildSshOptions($port, $identityFile);

        $copy = Process::timeout((int) config('alis_remote_backup_keys.process_timeout', 30))
            ->run([
                'scp',
                ...$sshOptions['scp'],
                $tempFile,
                $remote.':'.$remoteTempPath,
            ]);

        if ($copy->failed()) {
            @unlink($tempFile);
            throw new RuntimeException(trim($copy->errorOutput()) ?: 'Failed to upload authorized_keys to the backup server.');
        }

        $install = Process::timeout((int) config('alis_remote_backup_keys.process_timeout', 30))
            ->run([
                'ssh',
                ...$sshOptions['ssh'],
                $remote,
                (string) config('alis_remote_backup_keys.install_command'),
                $remoteTempPath,
            ]);

        @unlink($tempFile);

        if ($install->failed()) {
            throw new RuntimeException(trim($install->errorOutput()) ?: 'Failed to install authorized_keys on the backup server.');
        }
    }

    private function buildSshOptions(string $port, mixed $identityFile): array
    {
        $ssh = ['-p', $port, '-o', 'BatchMode=yes'];
        $scp = ['-P', $port, '-o', 'BatchMode=yes'];

        if (filled($identityFile)) {
            $ssh = [...$ssh, '-i', (string) $identityFile];
            $scp = [...$scp, '-i', (string) $identityFile];
        }

        return [
            'ssh' => $ssh,
            'scp' => $scp,
        ];
    }
}
