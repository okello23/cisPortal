<?php

namespace App\Livewire;

use App\Models\AlisRemoteBackupDeployment;
use App\Models\AlisRemoteBackupKey;
use App\Models\AlisRemoteBackupKeyHistory;
use App\Models\AlisRemoteBackupKeyUpdateReason;
use App\Models\Facility;
use App\Support\AlisRemoteBackupDeploymentService;
use App\Support\AlisRemoteBackupKeyService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

class AlisRemoteBackupKeysManager extends Component
{
    public string $facilityId = '';

    public string $keySearch = '';

    public string $publicKey = '';

    public string $reasonId = '';

    public string $comments = '';

    public ?string $flashMessage = null;

    public ?string $flashError = null;

    public bool $showKeyModal = false;

    public function mount(): void
    {
        $this->facilityId = request()->string('facility_id')->toString();
    }

    public function updatedFacilityId(): void
    {
        $this->loadSelectedKeyIntoForm();
    }

    public function openCreateModal(): void
    {
        $this->showKeyModal = true;
        $this->facilityId = '';
        $this->publicKey = '';
        $this->reasonId = '';
        $this->comments = '';
        $this->resetValidation();
    }

    public function openEditModal(int $facilityId): void
    {
        $this->showKeyModal = true;
        $this->facilityId = (string) $facilityId;
        $this->loadSelectedKeyIntoForm();
    }

    public function closeKeyModal(): void
    {
        $this->showKeyModal = false;
        $this->resetValidation();
    }

    public function saveKey(AlisRemoteBackupKeyService $keyService): void
    {
        $this->flashMessage = null;
        $this->flashError = null;

        $this->validate([
            'facilityId' => ['required', 'exists:facilities,id'],
            'publicKey' => ['required', 'string'],
            'reasonId' => [$this->selectedKey() ? 'required' : 'nullable', 'nullable', 'exists:alis_remote_backup_key_update_reasons,id'],
            'comments' => ['nullable', 'string'],
        ], [
            'facilityId.required' => 'Please select a facility.',
            'reasonId.required' => 'Please provide a reason for updating the existing SSH key.',
        ]);

        try {
            $facility = Facility::query()->findOrFail((int) $this->facilityId);
            $reason = $this->reasonId !== '' ? AlisRemoteBackupKeyUpdateReason::query()->findOrFail((int) $this->reasonId) : null;
            $keyService->saveKey($facility, $this->publicKey, Auth::user(), $reason, $this->comments);

            $this->publicKey = $this->selectedKey()?->public_key ?? $this->publicKey;
            $this->reasonId = '';
            $this->comments = '';
            $this->flashMessage = 'Key saved successfully. Pending deployment.';
            $this->showKeyModal = false;
        } catch (ValidationException $exception) {
            $this->setErrorBag($exception->validator->getMessageBag());
            $this->flashError = 'Please review the key details and try again.';
        }
    }

    public function deployKeys(AlisRemoteBackupDeploymentService $deploymentService): void
    {
        $this->flashMessage = null;
        $this->flashError = null;

        $deployment = $deploymentService->deploy(Auth::user());

        if ($deployment->status === 'success') {
            $this->flashMessage = 'All active SSH keys were deployed successfully.';

            return;
        }

        $this->flashError = $deployment->error_message ?: 'Deployment finished with issues.';
    }

    public function toggleKeyStatus(int $keyId): void
    {
        $key = AlisRemoteBackupKey::query()->findOrFail($keyId);
        $isDeactivating = $key->status === 'active';

        $key->forceFill([
            'status' => $isDeactivating ? 'inactive' : 'active',
            'deployment_status' => 'pending',
            'updated_by' => Auth::id(),
        ])->save();

        AlisRemoteBackupKeyHistory::query()->create([
            'facility_id' => $key->facility_id,
            'action' => $isDeactivating ? 'revoked' : 'updated',
            'old_public_key' => $key->public_key,
            'new_public_key' => $key->public_key,
            'old_fingerprint' => $key->fingerprint,
            'new_fingerprint' => $key->fingerprint,
            'performed_by' => Auth::id(),
            'performed_at' => now(),
            'comments' => $isDeactivating ? 'Key deactivated from registry.' : 'Key reactivated from registry.',
        ]);

        $this->flashMessage = $isDeactivating ? 'Key deactivated. Pending deployment.' : 'Key reactivated. Pending deployment.';
    }

    public function render(): View
    {
        return view('livewire.alis-remote-backup-keys-manager', [
            'facilities' => $this->facilityOptions(),
            'keys' => $this->keyRows(),
            'selectedFacility' => $this->selectedFacility(),
            'selectedKey' => $this->selectedKey(),
            'history' => $this->selectedHistory(),
            'activeReasons' => AlisRemoteBackupKeyUpdateReason::query()->where('active', true)->orderBy('name')->get(),
            'latestDeployment' => AlisRemoteBackupDeployment::query()->with('deployer')->latest('deployed_at')->first(),
            'pendingCount' => AlisRemoteBackupKey::query()->where('status', 'active')->where('deployment_status', 'pending')->count(),
            'failedCount' => AlisRemoteBackupKey::query()->where('status', 'active')->where('deployment_status', 'failed')->count(),
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

    private function facilityOptions(): Collection
    {
        return Facility::query()
            ->with('region')
            ->where('active', true)
            ->orderBy('name')
            ->get();
    }

    private function keyRows(): Collection
    {
        return AlisRemoteBackupKey::query()
            ->with(['facility.region', 'creator'])
            ->when(trim($this->keySearch) !== '', function (Builder $query) {
                $search = '%'.trim($this->keySearch).'%';

                $query->where(function (Builder $inner) use ($search) {
                    $inner->whereHas('facility', function (Builder $facility) use ($search) {
                        $facility->where('name', 'like', $search)
                            ->orWhere('district_name', 'like', $search)
                            ->orWhere('code', 'like', $search)
                            ->orWhereHas('region', fn (Builder $region) => $region->where('name', 'like', $search));
                    })->orWhere('fingerprint', 'like', $search);
                });
            })
            ->latest('created_at')
            ->get();
    }

    private function loadSelectedKeyIntoForm(): void
    {
        $this->resetValidation(['publicKey', 'reasonId', 'comments']);
        $this->flashError = null;
        $this->flashMessage = null;
        $this->reasonId = '';
        $this->comments = '';
        $this->publicKey = $this->selectedKey()?->public_key ?? '';
    }
}
