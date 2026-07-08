<div class="content-card bg-white p-4 mt-4">
    @if ($flashMessage)
        <div class="alert alert-success rounded-4">{{ $flashMessage }}</div>
    @endif

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <h2 class="h5 mb-0">A-LIS Key Update Reasons</h2>
        @if ($editingReasonId)
            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill" wire:click="resetReasonForm">Cancel Edit</button>
        @endif
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-5">
            <label class="form-label">Reason Name</label>
            <input type="text" class="form-control" wire:model="reasonName">
        </div>
        <div class="col-md-5">
            <label class="form-label">Description</label>
            <input type="text" class="form-control" wire:model="reasonDescription">
        </div>
        <div class="col-md-2 d-flex align-items-end">
            <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" wire:model="reasonActive" id="dashboardReasonActive">
                <label class="form-check-label" for="dashboardReasonActive">Active</label>
            </div>
        </div>
        <div class="col-12">
            <button type="button" class="btn btn-outline-dark rounded-pill px-4" wire:click="saveReason">
                {{ $editingReasonId ? 'Update Reason' : 'Add Reason' }}
            </button>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>Reason</th>
                    <th>Status</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($reasons as $reason)
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $reason->name }}</div>
                            <div class="small text-muted">{{ $reason->description ?: 'No description' }}</div>
                        </td>
                        <td>{{ $reason->active ? 'Active' : 'Inactive' }}</td>
                        <td class="text-end">
                            <div class="d-flex justify-content-end gap-2">
                                <button type="button" class="btn btn-sm btn-outline-dark rounded-pill" wire:click="editReason({{ $reason->id }})">Edit</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill" wire:click="toggleReason({{ $reason->id }})">
                                    {{ $reason->active ? 'Deactivate' : 'Activate' }}
                                </button>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
