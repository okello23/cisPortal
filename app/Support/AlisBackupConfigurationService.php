<?php

namespace App\Support;

use App\Models\AlisBackupConfiguration;
use App\Models\AlisRemoteBackupKeyUpdateReason;
use App\Models\Facility;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AlisBackupConfigurationService
{
    public function __construct(
        private readonly BackupDirectoryNameGenerator $directoryNameGenerator,
        private readonly AlisRemoteBackupKeyService $keyService,
        private readonly AlisBackupProvisioningService $provisioningService,
    ) {
    }

    public function saveConfiguration(
        Facility $facility,
        string $databaseName,
        string $databaseUsername,
        ?string $databasePassword,
        string $publicKeyInput,
        User $user,
        ?AlisRemoteBackupKeyUpdateReason $reason = null,
        ?string $comments = null,
    ): AlisBackupConfiguration {
        $existing = AlisBackupConfiguration::query()->where('facility_id', $facility->id)->first();
        $directoryName = $this->directoryNameGenerator->generate($facility->name);
        $databaseName = trim($databaseName);
        $databaseUsername = trim($databaseUsername);
        $databasePassword = $databasePassword !== null ? trim($databasePassword) : null;

        if ($databaseName === '') {
            throw ValidationException::withMessages(['databaseName' => 'A-LIS database name is required.']);
        }

        if ($databaseUsername === '') {
            throw ValidationException::withMessages(['databaseUsername' => 'A-LIS database username is required.']);
        }

        if (! $existing && blank($databasePassword)) {
            throw ValidationException::withMessages(['databasePassword' => 'A-LIS database password is required.']);
        }

        if ($directoryName === '' || ! preg_match('/^[a-z0-9_]+$/', $directoryName)) {
            throw ValidationException::withMessages(['backupDirectoryName' => 'A valid backup directory name could not be generated for the selected facility.']);
        }

        $duplicateDirectory = AlisBackupConfiguration::query()
            ->where('backup_directory_name', $directoryName)
            ->when($existing, fn ($query) => $query->where('id', '!=', $existing->id))
            ->exists();

        if ($duplicateDirectory) {
            throw ValidationException::withMessages(['facilityId' => 'Another facility already uses this generated backup directory name.']);
        }

        $configuration = DB::transaction(function () use (
            $existing,
            $facility,
            $directoryName,
            $databaseName,
            $databaseUsername,
            $databasePassword,
            $publicKeyInput,
            $user,
            $reason,
            $comments,
        ) {
            $this->keyService->saveKey($facility, $publicKeyInput, $user, $reason, $comments);

            $payload = [
                'backup_directory_name' => $directoryName,
                'database_name' => $databaseName,
                'database_username' => $databaseUsername,
                'status' => AlisBackupConfiguration::STATUS_PENDING_PROVISIONING,
                'updated_by' => $user->id,
                'last_provisioning_error' => null,
            ];

            if (filled($databasePassword)) {
                $payload['database_password'] = $databasePassword;
            }

            if ($existing) {
                $existing->fill($payload);
                $existing->save();

                return $existing->fresh();
            }

            return AlisBackupConfiguration::query()->create([
                ...$payload,
                'facility_id' => $facility->id,
                'created_by' => $user->id,
            ]);
        });

        return $this->provisioningService->provision($configuration, $user);
    }
}
