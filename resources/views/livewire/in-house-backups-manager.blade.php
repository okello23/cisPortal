<div>
    @if (session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
    @if (session('error')) <div class="alert alert-danger">{{ session('error') }}</div> @endif
    <div class="card shadow-sm">
        <div class="card-body">
            <div class="d-flex justify-content-between gap-3 mb-3">
                <input wire:model.live.debounce.300ms="search" class="form-control" placeholder="Search systems, servers, or database type">
                <button wire:click="create" class="btn btn-dark text-nowrap">Add System Backup</button>
            </div>
            <div class="table-responsive"><table class="table align-middle mb-0">
                <thead><tr><th>System</th><th>Server</th><th>Database</th><th>Backup directory</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                @forelse($configurations as $configuration)
                    <tr><td class="fw-semibold">{{ $configuration->system_name }}</td><td>{{ $configuration->server_name ?: '—' }}</td><td><span class="text-uppercase">{{ $configuration->database_type }}</span><br><small class="text-muted">{{ $configuration->database_name }}</small></td><td class="font-monospace small">{{ $configuration->backup_directory_name }}</td><td><span class="badge {{ $configuration->status === 'active' ? 'bg-success' : ($configuration->status === 'provisioning_failed' ? 'bg-danger' : 'bg-secondary') }}">{{ ucwords(str_replace('_', ' ', $configuration->status)) }}</span>@if($configuration->last_provisioning_error)<small class="d-block text-danger mt-1">{{ $configuration->last_provisioning_error }}</small>@endif</td><td class="text-end"><div class="btn-group"><button wire:click="edit({{ $configuration->id }})" class="btn btn-sm btn-outline-dark">Edit</button>@if($configuration->status === 'active')<a class="btn btn-sm btn-outline-success" href="{{ route('infrastructure.in-house-backups.download-script', $configuration) }}">Script</a>@endif<button wire:click="toggleStatus({{ $configuration->id }})" class="btn btn-sm btn-outline-secondary">{{ $configuration->status === 'active' ? 'Disable' : 'Enable' }}</button></div></td></tr>
                @empty <tr><td colspan="6" class="text-center text-muted py-4">No in-house backup configurations have been saved yet.</td></tr> @endforelse
                </tbody>
            </table></div>
        </div>
    </div>
    @if($showModal)
    <div class="modal d-block" tabindex="-1" style="background:rgba(0,0,0,.45)"><div class="modal-dialog"><form wire:submit="save" class="modal-content"><div class="modal-header"><h2 class="modal-title h5">{{ $editingId ? 'Update' : 'Add' }} In-House Backup</h2><button type="button" class="btn-close" wire:click="$set('showModal', false)"></button></div><div class="modal-body"><p class="small text-muted">The selected engine determines the backup command in the generated script.</p><div class="mb-3"><label class="form-label">System</label>@if($editingId)<input class="form-control" value="{{ $systemName }}" readonly><input wire:model="systemId" type="hidden">@else<select wire:model="systemId" class="form-select"><option value="">Select a system</option>@foreach($systems as $system)<option value="{{ $system->id }}">{{ $system->name }}</option>@endforeach</select>@endif @error('systemId')<small class="text-danger">{{ $message }}</small>@enderror</div><div class="mb-3"><label class="form-label">Server / host</label><input wire:model="serverName" class="form-control" placeholder="e.g. 10.200.0.20"></div><div class="mb-3"><label class="form-label">Database engine</label><select wire:model="databaseType" class="form-select"><option value="mysql">MySQL</option><option value="postgresql">PostgreSQL</option><option value="mongodb">MongoDB</option></select></div><div class="mb-3"><label class="form-label">Database name</label><input wire:model="databaseName" class="form-control"></div><div class="mb-3"><label class="form-label">Database username</label><input wire:model="databaseUsername" class="form-control"></div><div><label class="form-label">Database password {{ $editingId ? '(leave blank to keep current)' : '' }}</label><input wire:model="databasePassword" type="password" class="form-control">@error('databasePassword')<small class="text-danger">{{ $message }}</small>@enderror</div></div><div class="modal-footer"><button type="button" wire:click="$set('showModal', false)" class="btn btn-outline-secondary">Cancel</button><button class="btn btn-dark">Save configuration</button></div></form></div></div>
    @endif
</div>
