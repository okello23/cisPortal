<?php

namespace App\Livewire;

use App\Models\InHouseBackupConfiguration;
use App\Models\SupportSystem;
use App\Models\User;
use App\Support\InHouseBackupProvisioningService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Component;

class InHouseBackupsManager extends Component
{
    public ?int $editingId = null;
    public string $systemId = '';
    public string $systemName = '';
    public string $serverName = '';
    public string $databaseType = 'mysql';
    public string $databaseName = '';
    public string $databaseUsername = '';
    public string $databasePassword = '';
    public bool $showModal = false;
    public string $search = '';

    public function mount(): void
    {
        abort_unless(Auth::user()?->hasAnyRole([User::ROLE_ICT_ADMIN, User::ROLE_ICT_SUPPORT_STAFF, User::ROLE_ICT_SUPERVISOR, User::ROLE_DEVELOPER]), 403);
    }

    public function create(): void
    {
        $this->reset(['editingId', 'systemId', 'systemName', 'serverName', 'databaseName', 'databaseUsername', 'databasePassword']);
        $this->databaseType = 'mysql';
        $this->resetValidation();
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $item = InHouseBackupConfiguration::findOrFail($id);
        $this->editingId = $item->id;
        $this->systemId = (string) $item->system_id;
        $this->systemName = $item->system_name;
        $this->serverName = $item->server_name ?? '';
        $this->databaseType = $item->database_type;
        $this->databaseName = $item->database_name;
        $this->databaseUsername = $item->database_username;
        $this->databasePassword = '';
        $this->resetValidation();
        $this->showModal = true;
    }

    public function save(): void
    {
        $rules = [
            'systemId' => ['required', 'exists:support_systems,id', 'unique:in_house_backup_configurations,system_id'.($this->editingId ? ','.$this->editingId : '')],
            'serverName' => ['nullable', 'string', 'max:255'],
            'databaseType' => ['required', 'in:'.implode(',', InHouseBackupConfiguration::DATABASE_TYPES)],
            'databaseName' => ['required', 'string', 'max:255'],
            'databaseUsername' => ['required', 'string', 'max:255'],
            'databasePassword' => [$this->editingId ? 'nullable' : 'required', 'string'],
        ];
        $data = $this->validate($rules);
        $system = SupportSystem::query()->where('active', true)->findOrFail($data['systemId']);
        $directory = Str::slug($system->code ?: $system->name, '_');
        $payload = [
            'system_id' => $system->id, 'system_name' => $system->name, 'server_name' => trim($data['serverName']),
            'database_type' => $data['databaseType'], 'database_name' => trim($data['databaseName']),
            'database_username' => trim($data['databaseUsername']), 'backup_directory_name' => $directory,
            'updated_by' => Auth::id(),
        ];
        if ($data['databasePassword'] !== '') $payload['database_password'] = $data['databasePassword'];
        if ($this->editingId) {
            $configuration = InHouseBackupConfiguration::findOrFail($this->editingId);
            $configuration->update($payload);
        } else {
            $configuration = InHouseBackupConfiguration::create([...$payload, 'database_password' => $payload['database_password'] ?? '', 'status' => 'pending_provisioning', 'created_by' => Auth::id()]);
            $configuration = app(InHouseBackupProvisioningService::class)->provision($configuration);
        }
        $this->showModal = false;
        session()->flash(
            $configuration->status === 'active' ? 'success' : 'error',
            $configuration->status === 'active'
                ? 'In-house backup configuration saved and central directory provisioned.'
                : ($configuration->last_provisioning_error ?: 'Backup configuration was saved, but provisioning failed.')
        );
    }

    public function toggleStatus(int $id): void
    {
        $item = InHouseBackupConfiguration::findOrFail($id);
        $item->update(['status' => $item->status === 'active' ? 'disabled' : 'active', 'updated_by' => Auth::id()]);
    }

    public function render(): View
    {
        return view('livewire.in-house-backups-manager', ['systems' => SupportSystem::query()
            ->where('active', true)
            ->whereDoesntHave('inHouseBackupConfiguration')
            ->orderBy('sort_order')->orderBy('name')->get(), 'configurations' => InHouseBackupConfiguration::query()
            ->when($this->search !== '', fn ($q) => $q->where(fn ($q) => $q->where('system_name', 'like', '%'.$this->search.'%')->orWhere('server_name', 'like', '%'.$this->search.'%')->orWhere('database_type', 'like', '%'.$this->search.'%')))
            ->latest()->get()]);
    }
}
