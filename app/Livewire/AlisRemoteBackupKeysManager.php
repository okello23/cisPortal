<?php

namespace App\Livewire;

use App\Models\AlisBackupConfiguration;
use App\Models\AlisRemoteBackupDeployment;
use App\Models\AlisRemoteBackupKey;
use App\Models\AlisRemoteBackupKeyHistory;
use App\Models\AlisRemoteBackupKeyUpdateReason;
use App\Models\Facility;
use App\Models\Region;
use App\Models\User;
use App\Support\AlisBackupConfigurationService;
use App\Support\AlisBackupProvisioningService;
use App\Support\AlisBackupStatusService;
use App\Support\AlisRemoteBackupDeploymentService;
use App\Support\BackupDirectoryNameGenerator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

class AlisRemoteBackupKeysManager extends Component
{
    public string $regionId = '';

    public string $districtName = '';

    public string $facilityId = '';

    public string $keySearch = '';

    public string $backupDirectoryName = '';

    public string $databaseName = '';

    public string $databaseUsername = '';

    public string $databasePassword = '';

    public string $publicKey = '';

    public string $reasonId = '';

    public string $comments = '';

    public ?string $flashMessage = null;

    public ?string $flashError = null;

    public bool $showKeyModal = false;

    public bool $showFacilityDetailsModal = false;

    public ?int $confirmingDeactivationConfigurationId = null;

    public function mount(): void
    {
        abort_unless($this->canManage(Auth::user()), 403);

        $directoryNameGenerator = app(BackupDirectoryNameGenerator::class);
        $this->facilityId = request()->string('facility_id')->toString();
        $this->syncFiltersFromFacility();
        $this->normalizeSelections();
        $this->syncBackupDirectoryName($directoryNameGenerator);
        $this->loadSelectedConfigurationIntoForm($directoryNameGenerator);
    }

    public function updatedRegionId(): void
    {
        $directoryNameGenerator = app(BackupDirectoryNameGenerator::class);
        $this->districtName = '';
        $this->facilityId = '';
        $this->syncBackupDirectoryName($directoryNameGenerator);
        $this->loadSelectedConfigurationIntoForm($directoryNameGenerator);
    }

    public function updatedDistrictName(): void
    {
        $directoryNameGenerator = app(BackupDirectoryNameGenerator::class);
        $this->facilityId = '';
        $this->syncBackupDirectoryName($directoryNameGenerator);
        $this->loadSelectedConfigurationIntoForm($directoryNameGenerator);
    }

    public function updatedFacilityId(): void
    {
        $directoryNameGenerator = app(BackupDirectoryNameGenerator::class);
        $this->syncFiltersFromFacility();
        $this->normalizeSelections();
        $this->syncBackupDirectoryName($directoryNameGenerator);
        $this->loadSelectedConfigurationIntoForm($directoryNameGenerator);
    }

    public function openCreateModal(): void
    {
        $directoryNameGenerator = app(BackupDirectoryNameGenerator::class);
        $this->showKeyModal = true;
        $this->regionId = '';
        $this->districtName = '';
        $this->facilityId = '';
        $this->syncBackupDirectoryName($directoryNameGenerator);
        $this->loadSelectedConfigurationIntoForm($directoryNameGenerator);
        $this->resetValidation();
    }

    public function openEditModal(int $facilityId): void
    {
        $directoryNameGenerator = app(BackupDirectoryNameGenerator::class);
        $this->showKeyModal = true;
        $this->facilityId = (string) $facilityId;
        $this->syncFiltersFromFacility();
        $this->normalizeSelections();
        $this->syncBackupDirectoryName($directoryNameGenerator);
        $this->loadSelectedConfigurationIntoForm($directoryNameGenerator);
    }

    public function openFacilityDetails(int $facilityId): void
    {
        $directoryNameGenerator = app(BackupDirectoryNameGenerator::class);
        $this->facilityId = (string) $facilityId;
        $this->syncFiltersFromFacility();
        $this->normalizeSelections();
        $this->syncBackupDirectoryName($directoryNameGenerator);
        $this->loadSelectedConfigurationIntoForm($directoryNameGenerator);
        $this->showFacilityDetailsModal = true;
    }

    public function closeFacilityDetailsModal(): void
    {
        $this->showFacilityDetailsModal = false;
    }

    public function confirmDeactivation(int $configurationId): void
    {
        $this->confirmingDeactivationConfigurationId = $configurationId;
    }

    public function cancelDeactivation(): void
    {
        $this->confirmingDeactivationConfigurationId = null;
    }

    public function deactivateConfirmedConfiguration(): void
    {
        if ($this->confirmingDeactivationConfigurationId === null) {
            return;
        }

        $configurationId = $this->confirmingDeactivationConfigurationId;
        $this->confirmingDeactivationConfigurationId = null;

        $this->deactivateConfiguration($configurationId);
    }

    public function activateConfiguration(int $configurationId): void
    {
        $this->setConfigurationStatus($configurationId, false);
    }

    public function deactivateConfiguration(int $configurationId): void
    {
        $this->setConfigurationStatus($configurationId, true);
    }

    public function closeKeyModal(): void
    {
        $this->showKeyModal = false;
        $this->databasePassword = '';
        $this->reasonId = '';
        $this->comments = '';
        $this->resetValidation();
    }

    public function saveConfiguration(): void
    {
        $configurationService = app(AlisBackupConfigurationService::class);
        $this->flashMessage = null;
        $this->flashError = null;

        $this->validate([
            'facilityId' => ['required', 'exists:facilities,id'],
            'databaseName' => ['required', 'string'],
            'databaseUsername' => ['required', 'string'],
            'databasePassword' => [$this->selectedConfiguration() ? 'nullable' : 'required', 'nullable', 'string'],
            'publicKey' => ['required', 'string'],
            'reasonId' => ['nullable', 'exists:alis_remote_backup_key_update_reasons,id'],
            'comments' => ['nullable', 'string'],
        ], [
            'facilityId.required' => 'Please select a facility.',
            'databaseName.required' => 'A-LIS database name is required.',
            'databaseUsername.required' => 'A-LIS database username is required.',
            'databasePassword.required' => 'A-LIS database password is required.',
            'publicKey.required' => 'SSH public key is required.',
        ]);

        try {
            $facility = Facility::query()->findOrFail((int) $this->facilityId);
            $reason = $this->reasonId !== '' ? AlisRemoteBackupKeyUpdateReason::query()->findOrFail((int) $this->reasonId) : null;

            $configuration = $configurationService->saveConfiguration(
                $facility,
                $this->databaseName,
                $this->databaseUsername,
                $this->databasePassword,
                $this->publicKey,
                Auth::user(),
                $reason,
                $this->comments,
            );

            $this->databasePassword = '';
            $this->reasonId = '';
            $this->comments = '';
            $this->showKeyModal = false;

            if ($configuration->status === AlisBackupConfiguration::STATUS_PROVISIONED) {
                $this->flashMessage = 'Backup configuration saved successfully. The facility backup directory has been created on the central backup server. Download the generated backup script and copy it to the A-LIS server.';
            } else {
                $this->flashError = $configuration->last_provisioning_error ?: 'Backup configuration was saved, but provisioning did not complete successfully.';
            }
        } catch (ValidationException $exception) {
            $this->setErrorBag($exception->validator->getMessageBag());
        }
    }

    public function retryProvisioning(int $configurationId): void
    {
        $provisioningService = app(AlisBackupProvisioningService::class);
        $this->flashMessage = null;
        $this->flashError = null;

        $configuration = AlisBackupConfiguration::query()->findOrFail($configurationId);
        $this->facilityId = (string) $configuration->facility_id;

        $configuration = $provisioningService->provision($configuration, Auth::user());

        if ($configuration->status === AlisBackupConfiguration::STATUS_PROVISIONED) {
            $this->flashMessage = 'Provisioning completed successfully. The backup script is ready to download.';

            return;
        }

        $this->flashError = $configuration->last_provisioning_error ?: 'Provisioning failed. Please try again.';
    }

    private function setConfigurationStatus(int $configurationId, bool $isDisabling): void
    {
        $provisioningService = app(AlisBackupProvisioningService::class);
        $this->flashMessage = null;
        $this->flashError = null;

        $configuration = AlisBackupConfiguration::query()->findOrFail($configurationId);
        $key = AlisRemoteBackupKey::query()
            ->where('facility_id', $configuration->facility_id)
            ->latest('updated_at')
            ->firstOrFail();

        $key->forceFill([
            'status' => $isDisabling ? 'inactive' : 'active',
            'deployment_status' => 'pending',
            'updated_by' => Auth::id(),
        ])->save();

        AlisRemoteBackupKeyHistory::query()->create([
            'facility_id' => $key->facility_id,
            'action' => $isDisabling ? 'revoked' : 'updated',
            'old_public_key' => $key->public_key,
            'new_public_key' => $key->public_key,
            'old_fingerprint' => $key->fingerprint,
            'new_fingerprint' => $key->fingerprint,
            'performed_by' => Auth::id(),
            'performed_at' => now(),
            'comments' => $isDisabling ? 'Backup configuration disabled.' : 'Backup configuration re-enabled.',
        ]);

        if ($isDisabling) {
            $configuration->forceFill([
                'status' => AlisBackupConfiguration::STATUS_DISABLED,
                'updated_by' => Auth::id(),
                'last_provisioning_error' => null,
            ])->save();

            $deployment = app(AlisRemoteBackupDeploymentService::class)->deploy(Auth::user());

            if ($deployment->status === 'success') {
                $this->flashMessage = 'Backup configuration disabled.';

                return;
            }

            $this->flashError = 'Backup configuration was disabled, but the key removal deployment did not complete successfully.';

            return;
        }

        $configuration->forceFill([
            'status' => AlisBackupConfiguration::STATUS_PENDING_PROVISIONING,
            'updated_by' => Auth::id(),
            'last_provisioning_error' => null,
        ])->save();

        $configuration = $provisioningService->provision($configuration, Auth::user());

        if ($configuration->status === AlisBackupConfiguration::STATUS_PROVISIONED) {
            $this->flashMessage = 'Backup configuration re-enabled and provisioned successfully.';

            return;
        }

        $this->flashError = $configuration->last_provisioning_error ?: 'Backup configuration was re-enabled, but provisioning failed.';
    }

    public function render(): View
    {
        $configurations = $this->configurationRows();

        return view('livewire.alis-remote-backup-keys-manager', [
            'regions' => Region::query()->where('active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'districtOptions' => $this->districtOptions(),
            'facilities' => $this->facilityOptions(),
            'configurations' => $configurations,
            'lastBackups' => app(AlisBackupStatusService::class)->latestBackups(
                $configurations->pluck('backup_directory_name')
            ),
            'selectedFacility' => $this->selectedFacility(),
            'selectedConfiguration' => $this->selectedConfiguration(),
            'selectedKey' => $this->selectedKey(),
            'history' => $this->selectedHistory(),
            'activeReasons' => AlisRemoteBackupKeyUpdateReason::query()->where('active', true)->orderBy('name')->get(),
            'latestDeployment' => AlisRemoteBackupDeployment::query()->with('deployer')->latest('deployed_at')->first(),
            'pendingCount' => AlisBackupConfiguration::query()->where('status', AlisBackupConfiguration::STATUS_PENDING_PROVISIONING)->count(),
            'failedCount' => AlisBackupConfiguration::query()->where('status', AlisBackupConfiguration::STATUS_PROVISIONING_FAILED)->count(),
            'provisionedCount' => AlisBackupConfiguration::query()->where('status', AlisBackupConfiguration::STATUS_PROVISIONED)->count(),
        ]);
    }

    #[Computed]
    public function selectedFacility(): ?Facility
    {
        if ($this->facilityId === '') {
            return null;
        }

        return Facility::query()
            ->with('region')
            ->find((int) $this->facilityId);
    }

    #[Computed]
    public function selectedConfiguration(): ?AlisBackupConfiguration
    {
        if ($this->facilityId === '') {
            return null;
        }

        return AlisBackupConfiguration::query()
            ->where('facility_id', (int) $this->facilityId)
            ->first();
    }

    #[Computed]
    public function selectedKey(): ?AlisRemoteBackupKey
    {
        if ($this->facilityId === '') {
            return null;
        }

        return AlisRemoteBackupKey::query()
            ->where('facility_id', (int) $this->facilityId)
            ->latest('updated_at')
            ->first();
    }

    private function selectedHistory(): Collection
    {
        if ($this->facilityId === '') {
            return collect();
        }

        return AlisRemoteBackupKeyHistory::query()
            ->with(['reason', 'performer', 'deployment'])
            ->where('facility_id', (int) $this->facilityId)
            ->latest('performed_at')
            ->limit(15)
            ->get();
    }

    private function configurationRows(): Collection
    {
        return AlisBackupConfiguration::query()
            ->with(['facility.region', 'creator'])
            ->when(trim($this->keySearch) !== '', function (Builder $query) {
                $search = '%'.trim($this->keySearch).'%';

                $query->where(function (Builder $inner) use ($search) {
                    $inner->where('backup_directory_name', 'like', $search)
                        ->orWhere('status', 'like', $search)
                        ->orWhereHas('facility', function (Builder $facility) use ($search) {
                            $facility->where('name', 'like', $search)
                                ->orWhere('district_name', 'like', $search)
                                ->orWhere('code', 'like', $search)
                                ->orWhereHas('region', fn (Builder $region) => $region->where('name', 'like', $search));
                        });
                });
            })
            ->latest('updated_at')
            ->get();
    }

    private function districtOptions(): Collection
    {
        return Facility::query()
            ->where('active', true)
            ->when($this->regionId !== '', fn ($query) => $query->where('region_id', (int) $this->regionId))
            ->whereNotNull('district_name')
            ->where('district_name', '!=', '')
            ->distinct()
            ->orderBy('district_name')
            ->pluck('district_name')
            ->values();
    }

    private function facilityOptions(): Collection
    {
        return Facility::query()
            ->with('region')
            ->where('active', true)
            ->when($this->regionId !== '', fn ($query) => $query->where('region_id', (int) $this->regionId))
            ->when($this->districtName !== '', fn ($query) => $query->where('district_name', $this->districtName))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    private function loadSelectedConfigurationIntoForm(BackupDirectoryNameGenerator $directoryNameGenerator): void
    {
        $this->resetValidation([
            'backupDirectoryName',
            'databaseName',
            'databaseUsername',
            'databasePassword',
            'publicKey',
            'reasonId',
            'comments',
        ]);

        $this->reasonId = '';
        $this->comments = '';
        $this->databasePassword = '';

        $facility = $this->selectedFacility();

        if (! $facility) {
            $this->backupDirectoryName = '';
            $this->databaseName = '';
            $this->databaseUsername = '';
            $this->publicKey = '';

            return;
        }

        $configuration = $this->selectedConfiguration();
        $this->backupDirectoryName = $configuration?->backup_directory_name
            ?? $directoryNameGenerator->generate($facility->name);
        $this->databaseName = $configuration?->database_name ?? '';
        $this->databaseUsername = $configuration?->database_username ?? '';
        $this->publicKey = $this->selectedKey()?->public_key ?? '';
    }

    private function syncFiltersFromFacility(): void
    {
        if ($this->facilityId === '') {
            return;
        }

        $facility = Facility::query()
            ->select('id', 'region_id', 'district_name')
            ->find((int) $this->facilityId);

        if (! $facility) {
            $this->facilityId = '';

            return;
        }

        $this->regionId = $facility->region_id ? (string) $facility->region_id : '';
        $this->districtName = $facility->district_name ?? '';
    }

    private function syncBackupDirectoryName(BackupDirectoryNameGenerator $directoryNameGenerator): void
    {
        $facility = $this->selectedFacility();
        $this->backupDirectoryName = $facility
            ? $directoryNameGenerator->generate($facility->name)
            : '';
    }

    private function normalizeSelections(): void
    {
        if ($this->districtName !== '' && ! $this->districtOptions()->contains($this->districtName)) {
            $this->districtName = '';
        }

        if ($this->facilityId === '') {
            return;
        }

        $facilityExists = $this->facilityOptions()
            ->contains(fn (Facility $facility) => (string) $facility->id === $this->facilityId);

        if (! $facilityExists) {
            $this->facilityId = '';
        }
    }

    private function canManage(?User $user): bool
    {
        return $user?->hasAnyRole([
            User::ROLE_ICT_ADMIN,
            User::ROLE_ICT_SUPPORT_STAFF,
            User::ROLE_ICT_SUPERVISOR,
            User::ROLE_DEVELOPER,
        ]) ?? false;
    }
}
