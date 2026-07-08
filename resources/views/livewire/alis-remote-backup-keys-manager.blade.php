<div class="row g-4" wire:loading.class="opacity-90">
    <div class="col-lg-7">
        @if ($flashMessage)
            <div class="alert alert-success rounded-4">{{ $flashMessage }}</div>
        @endif

        @if ($flashError)
            <div class="alert alert-danger rounded-4">{{ $flashError }}</div>
        @endif

        <div class="content-card bg-white p-4 p-lg-5 mb-4">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
                <div>
                    <h2 class="h4 mb-1">Registered Backup Keys</h2>
                    <p class="text-muted mb-0">Review facility SSH keys, update existing entries, and keep deployment state visible from one registry view.</p>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-dark rounded-pill px-4" wire:click="openCreateModal">
                        Add Key
                    </button>
                    <button
                        type="button"
                        class="btn btn-success rounded-pill px-4"
                        wire:click="deployKeys"
                        wire:loading.attr="disabled"
                        @disabled($pendingCount === 0)
                    >
                        Deploy Keys
                    </button>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-12">
                    <label class="form-label">Search Registered Keys</label>
                    <input
                        type="text"
                        class="form-control"
                        wire:model.live.debounce.250ms="keySearch"
                        placeholder="Search by facility, district, region, code, or fingerprint"
                    >
                </div>
            </div>

            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Facility</th>
                            <th>Added By</th>
                            <th>Date Added</th>
                            <th>Status</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($keys as $key)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $key->facility->name }}</div>
                                    <div class="small text-muted">
                                        {{ $key->facility->district_name ?: 'No district' }}
                                        @if ($key->facility->region?->name)
                                            | {{ $key->facility->region->name }}
                                        @endif
                                        @if ($key->facility->code)
                                            | {{ $key->facility->code }}
                                        @endif
                                    </div>
                                </td>
                                <td>{{ $key->creator?->name ?? 'System' }}</td>
                                <td>{{ $key->created_at?->format('Y-m-d H:i') }}</td>
                                <td>
                                    <span class="stats-badge {{ $key->status === 'active' ? 'stats-badge--teal' : 'stats-badge--slate' }}">
                                        {{ $key->status === 'active' ? 'Active' : 'Deactivated' }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div class="d-flex justify-content-end gap-2">
                                        <button type="button" class="btn btn-sm btn-outline-dark rounded-pill" wire:click="openEditModal({{ $key->facility_id }})">
                                            Edit
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill" wire:click="toggleKeyStatus({{ $key->id }})">
                                            {{ $key->status === 'active' ? 'Deactivate' : 'Activate' }}
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">No backup keys have been registered yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="content-card bg-white p-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                <h2 class="h5 mb-0">Audit History</h2>
                @if ($selectedFacility)
                    <span class="small text-muted">{{ $selectedFacility->name }}</span>
                @else
                    <span class="small text-muted">Pick a key entry to inspect its history.</span>
                @endif
            </div>

            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>When</th>
                            <th>Action</th>
                            <th>Reason</th>
                            <th>Fingerprint</th>
                            <th>By</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($history as $entry)
                            <tr>
                                <td>{{ $entry->performed_at?->format('Y-m-d H:i') }}</td>
                                <td>{{ str_replace('_', ' ', ucfirst($entry->action)) }}</td>
                                <td>{{ $entry->reason?->name ?? ($entry->comments ?: 'N/A') }}</td>
                                <td class="font-monospace small">{{ $entry->new_fingerprint ?? $entry->old_fingerprint ?? 'N/A' }}</td>
                                <td>{{ $entry->performer?->name ?? 'System' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">Select a facility entry from the registry to view audit history.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="content-card bg-white p-4 mb-4">
            <h2 class="h5 mb-3">Current Key Status</h2>
            @if ($selectedFacility)
                <div class="mb-3">
                    <div class="fw-semibold">{{ $selectedFacility->name }}</div>
                    <div class="small text-muted">
                        {{ $selectedFacility->district_name ?? 'No district' }}
                        @if ($selectedFacility->region?->name)
                            | {{ $selectedFacility->region->name }}
                        @endif
                        @if ($selectedFacility->code)
                            | {{ $selectedFacility->code }}
                        @endif
                    </div>
                </div>

                @if ($selectedKey)
                    <dl class="row mb-0">
                        <dt class="col-sm-5">Status</dt>
                        <dd class="col-sm-7">{{ $selectedKey->status === 'active' ? 'Active' : 'Deactivated' }}</dd>
                        <dt class="col-sm-5">Deployment</dt>
                        <dd class="col-sm-7 text-capitalize">{{ $selectedKey->deployment_status }}</dd>
                        <dt class="col-sm-5">Fingerprint</dt>
                        <dd class="col-sm-7 font-monospace small">{{ $selectedKey->fingerprint }}</dd>
                        <dt class="col-sm-5">Key Type</dt>
                        <dd class="col-sm-7">{{ $selectedKey->key_type }}</dd>
                        <dt class="col-sm-5">Comment</dt>
                        <dd class="col-sm-7">{{ $selectedKey->key_comment ?: 'N/A' }}</dd>
                    </dl>
                @else
                    <p class="text-muted mb-0">No SSH key is registered for this facility yet.</p>
                @endif
            @else
                <p class="text-muted mb-0">Choose a registry entry to inspect its current SSH key status.</p>
            @endif
        </div>

        <div class="content-card bg-white p-4 mb-4">
            <h2 class="h5 mb-3">Deployment Status</h2>
            <div class="row g-3 mb-3">
                <div class="col-6">
                    <div class="metric-card p-3 h-100">
                        <div class="fs-3 fw-bold">{{ $pendingCount }}</div>
                        <div class="text-muted small">Pending Changes</div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="metric-card p-3 h-100">
                        <div class="fs-3 fw-bold">{{ $failedCount }}</div>
                        <div class="text-muted small">Failed Keys</div>
                    </div>
                </div>
            </div>

            @if ($latestDeployment)
                <dl class="row mb-0">
                    <dt class="col-sm-5">Last Batch</dt>
                    <dd class="col-sm-7">{{ $latestDeployment->deployment_batch_reference }}</dd>
                    <dt class="col-sm-5">Status</dt>
                    <dd class="col-sm-7 text-capitalize">{{ $latestDeployment->status }}</dd>
                    <dt class="col-sm-5">Deployed At</dt>
                    <dd class="col-sm-7">{{ $latestDeployment->deployed_at?->format('Y-m-d H:i') ?? 'N/A' }}</dd>
                    <dt class="col-sm-5">Server</dt>
                    <dd class="col-sm-7">{{ $latestDeployment->backup_server ?: 'N/A' }}</dd>
                    <dt class="col-sm-5">Path</dt>
                    <dd class="col-sm-7 font-monospace small">{{ $latestDeployment->authorized_keys_path ?: 'N/A' }}</dd>
                </dl>

                @if ($latestDeployment->error_message)
                    <div class="alert alert-danger rounded-4 mt-3 mb-0">{{ $latestDeployment->error_message }}</div>
                @endif
            @else
                <p class="text-muted mb-0">No deployment has been recorded yet.</p>
            @endif
        </div>
    </div>

    @if ($showKeyModal)
        <div class="modal fade show d-block" tabindex="-1" aria-modal="true" role="dialog" style="background: rgba(10, 28, 43, 0.45);">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content border-0 rounded-4">
                    <div class="modal-header border-0 px-4 pt-4">
                        <div>
                            <h2 class="modal-title h4 mb-1">{{ $selectedKey ? 'Update SSH Key' : 'Add SSH Key' }}</h2>
                            <p class="text-muted mb-0">Register a facility key and mark it for the next full deployment.</p>
                        </div>
                        <button type="button" class="btn-close" wire:click="closeKeyModal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body px-4 pb-4">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">Facility</label>
                                <div wire:ignore>
                                    <select
                                        class="form-select js-facility-select2"
                                        data-placeholder="Search facility by name, district, region, or code"
                                        data-selected-value="{{ $facilityId }}"
                                    >
                                        <option value="">Select facility</option>
                                        @foreach ($facilities as $facility)
                                            <option value="{{ $facility->id }}">
                                                {{ $facility->name }}{{ $facility->district_name ? ' | '.$facility->district_name : '' }}{{ $facility->region?->name ? ' | '.$facility->region->name : '' }}{{ $facility->code ? ' | '.$facility->code : '' }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label">SSH Public Key</label>
                                <textarea
                                    class="form-control font-monospace"
                                    rows="6"
                                    wire:model="publicKey"
                                    placeholder="ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAA... alis-offsite-backup"
                                ></textarea>
                            </div>
                        </div>

                        @if ($selectedKey)
                            <div class="alert alert-warning rounded-4 mt-4 mb-0">
                                <div class="fw-semibold mb-2">An SSH key is already registered for this facility.</div>
                                <p class="mb-3">Please provide a reason before updating the key.</p>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Update Reason</label>
                                        <select class="form-select" wire:model="reasonId">
                                            <option value="">Select reason</option>
                                            @foreach ($activeReasons as $reason)
                                                <option value="{{ $reason->id }}">{{ $reason->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Comments</label>
                                        <input type="text" class="form-control" wire:model="comments" placeholder="Add supporting context if needed">
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                    <div class="modal-footer border-0 px-4 pb-4">
                        <button type="button" class="btn btn-outline-secondary rounded-pill px-4" wire:click="closeKeyModal">Cancel</button>
                        <button type="button" class="btn btn-dark rounded-pill px-4" wire:click="saveKey" wire:loading.attr="disabled">Save Key</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
