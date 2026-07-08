<?php

namespace App\Livewire;

use App\Models\AlisRemoteBackupKeyUpdateReason;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;

class AlisRemoteBackupKeyReasonsManager extends Component
{
    public string $reasonName = '';

    public string $reasonDescription = '';

    public bool $reasonActive = true;

    public ?int $editingReasonId = null;

    public ?string $flashMessage = null;

    public function saveReason(): void
    {
        abort_unless($this->canManageReasons(), 403);

        $validated = $this->validate([
            'reasonName' => ['required', 'string', 'max:255'],
            'reasonDescription' => ['nullable', 'string'],
            'reasonActive' => ['boolean'],
        ]);

        $reason = AlisRemoteBackupKeyUpdateReason::query()->updateOrCreate(
            ['id' => $this->editingReasonId],
            [
                'name' => trim($validated['reasonName']),
                'description' => trim((string) $validated['reasonDescription']) ?: null,
                'active' => $this->reasonActive,
                'created_by' => $this->editingReasonId ? null : Auth::id(),
                'updated_by' => Auth::id(),
            ]
        );

        $this->resetReasonForm();
        $this->flashMessage = $reason->wasRecentlyCreated ? 'Update reason created.' : 'Update reason updated.';
    }

    public function editReason(int $reasonId): void
    {
        abort_unless($this->canManageReasons(), 403);

        $reason = AlisRemoteBackupKeyUpdateReason::query()->findOrFail($reasonId);
        $this->editingReasonId = $reason->id;
        $this->reasonName = $reason->name;
        $this->reasonDescription = (string) $reason->description;
        $this->reasonActive = $reason->active;
    }

    public function toggleReason(int $reasonId): void
    {
        abort_unless($this->canManageReasons(), 403);

        $reason = AlisRemoteBackupKeyUpdateReason::query()->findOrFail($reasonId);
        $reason->forceFill([
            'active' => ! $reason->active,
            'updated_by' => Auth::id(),
        ])->save();

        $this->flashMessage = 'Update reason status changed.';
    }

    public function resetReasonForm(): void
    {
        $this->editingReasonId = null;
        $this->reasonName = '';
        $this->reasonDescription = '';
        $this->reasonActive = true;
    }

    public function render(): View
    {
        abort_unless($this->canManageReasons(), 403);

        return view('livewire.alis-remote-backup-key-reasons-manager', [
            'reasons' => AlisRemoteBackupKeyUpdateReason::query()->orderByDesc('active')->orderBy('name')->get(),
        ]);
    }

    private function canManageReasons(): bool
    {
        /** @var User|null $user */
        $user = Auth::user();

        return $user?->hasAnyRole([User::ROLE_ICT_ADMIN, User::ROLE_ICT_MANAGER, User::ROLE_ICT_SUPERVISOR]) ?? false;
    }
}
