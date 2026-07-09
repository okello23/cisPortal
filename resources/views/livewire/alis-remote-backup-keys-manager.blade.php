<div class="row g-4" wire:loading.class="opacity-90">
    <div class="col-lg-7">
        @if ($flashMessage && ! $showKeyModal)
            <div class="alert alert-success rounded-4">{{ $flashMessage }}</div>
        @endif

        @if ($flashError && ! $showKeyModal)
            <div class="alert alert-danger rounded-4">{{ $flashError }}</div>
        @endif

        <div class="content-card bg-white p-4 p-lg-5 mb-4">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
                <div>
                    <h2 class="h4 mb-1">Backup Configurations</h2>
                    <p class="text-muted mb-0">Manage facility database backup settings, central server provisioning, and script downloads in one place.</p>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-dark rounded-pill px-4" wire:click="openCreateModal">
                        Add Backup Configuration
                    </button>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-12">
                    <label class="form-label">Search Backup Configurations</label>
                    <input
                        type="text"
                        class="form-control"
                        wire:model.live.debounce.250ms="keySearch"
                        placeholder="Search by facility, district, region, code, or backup directory"
                    >
                </div>
            </div>

            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Facility</th>
                            <th>Backup Directory</th>
                            <th>Status</th>
                            <th>Updated</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($configurations as $configuration)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $configuration->facility->name }}</div>
                                    <div class="small text-muted">
                                        {{ $configuration->facility->district_name ?: 'No district' }}
                                        @if ($configuration->facility->region?->name)
                                            | {{ $configuration->facility->region->name }}
                                        @endif
                                        @if ($configuration->facility->code)
                                            | {{ $configuration->facility->code }}
                                        @endif
                                    </div>
                                </td>
                                <td class="font-monospace small">{{ $configuration->backup_directory_name }}</td>
                                <td>
                                    @php
                                        $badgeClass = match ($configuration->status) {
                                            \App\Models\AlisBackupConfiguration::STATUS_PROVISIONED => 'stats-badge--teal',
                                            \App\Models\AlisBackupConfiguration::STATUS_PROVISIONING_FAILED => 'stats-badge--red',
                                            \App\Models\AlisBackupConfiguration::STATUS_DISABLED => 'stats-badge--slate',
                                            default => 'stats-badge--orange',
                                        };
                                        $statusLabel = str_replace('_', ' ', ucfirst($configuration->status));
                                    @endphp
                                    <span class="stats-badge {{ $badgeClass }}">{{ $statusLabel }}</span>
                                </td>
                                <td>{{ $configuration->updated_at?->format('Y-m-d H:i') }}</td>
                                <td class="text-end">
                                    <div class="d-flex justify-content-end gap-2 flex-wrap">
                                        <button type="button" class="btn btn-sm btn-outline-dark rounded-pill" wire:click="openEditModal({{ $configuration->facility_id }})">
                                            Edit
                                        </button>
                                        @if ($configuration->status === \App\Models\AlisBackupConfiguration::STATUS_PROVISIONING_FAILED || $configuration->status === \App\Models\AlisBackupConfiguration::STATUS_PENDING_PROVISIONING)
                                            <button type="button" class="btn btn-sm btn-outline-warning rounded-pill" wire:click="retryProvisioning({{ $configuration->id }})">
                                                Retry Provisioning
                                            </button>
                                        @endif
                                        @if ($configuration->status === \App\Models\AlisBackupConfiguration::STATUS_PROVISIONED)
                                            @if (\Illuminate\Support\Facades\Route::has('infrastructure.alis-remote-backup-keys.download-script'))
                                                <a href="{{ route('infrastructure.alis-remote-backup-keys.download-script', $configuration) }}" class="btn btn-sm btn-success rounded-pill">
                                                    Download Backup Script
                                                </a>
                                            @else
                                                <button type="button" class="btn btn-sm btn-success rounded-pill" disabled>
                                                    Download Backup Script
                                                </button>
                                            @endif
                                        @endif
                                        <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill" wire:click="toggleConfigurationStatus({{ $configuration->id }})">
                                            {{ $configuration->status === \App\Models\AlisBackupConfiguration::STATUS_DISABLED ? 'Enable' : 'Disable' }}
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">No backup configurations have been saved yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="content-card bg-white p-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                <h2 class="h5 mb-0">SSH Key Audit History</h2>
                @if ($selectedFacility)
                    <span class="small text-muted">{{ $selectedFacility->name }}</span>
                @else
                    <span class="small text-muted">Pick a configuration entry to inspect its SSH key history.</span>
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
            <h2 class="h5 mb-3">Selected Configuration</h2>
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

                @if ($selectedConfiguration)
                    <dl class="row mb-0">
                        <dt class="col-sm-5">Backup Directory</dt>
                        <dd class="col-sm-7 font-monospace small">{{ $selectedConfiguration->backup_directory_name }}</dd>
                        <dt class="col-sm-5">Status</dt>
                        <dd class="col-sm-7 text-capitalize">{{ str_replace('_', ' ', $selectedConfiguration->status) }}</dd>
                        <dt class="col-sm-5">DB Name</dt>
                        <dd class="col-sm-7">{{ $selectedConfiguration->database_name }}</dd>
                        <dt class="col-sm-5">DB Username</dt>
                        <dd class="col-sm-7">{{ $selectedConfiguration->database_username }}</dd>
                        <dt class="col-sm-5">Provisioned At</dt>
                        <dd class="col-sm-7">{{ $selectedConfiguration->provisioned_at?->format('Y-m-d H:i') ?? 'N/A' }}</dd>
                    </dl>

                    @if ($selectedConfiguration->last_provisioning_error)
                        <div class="alert alert-danger rounded-4 mt-3 mb-0">{{ $selectedConfiguration->last_provisioning_error }}</div>
                    @endif

                    <div class="d-flex gap-2 flex-wrap mt-3">
                        @if ($selectedConfiguration->status === \App\Models\AlisBackupConfiguration::STATUS_PROVISIONED)
                            @if (\Illuminate\Support\Facades\Route::has('infrastructure.alis-remote-backup-keys.download-script'))
                                <a href="{{ route('infrastructure.alis-remote-backup-keys.download-script', $selectedConfiguration) }}" class="btn btn-success rounded-pill">
                                    Download Backup Script
                                </a>
                            @else
                                <button type="button" class="btn btn-success rounded-pill" disabled>
                                    Download Backup Script
                                </button>
                            @endif
                        @endif
                        @if ($selectedConfiguration->status === \App\Models\AlisBackupConfiguration::STATUS_PROVISIONING_FAILED || $selectedConfiguration->status === \App\Models\AlisBackupConfiguration::STATUS_PENDING_PROVISIONING)
                            <button type="button" class="btn btn-outline-warning rounded-pill" wire:click="retryProvisioning({{ $selectedConfiguration->id }})">
                                Retry Provisioning
                            </button>
                        @endif
                    </div>
                @else
                    <p class="text-muted mb-0">No backup configuration is registered for this facility yet.</p>
                @endif
            @else
                <p class="text-muted mb-0">Choose a configuration entry to inspect its current backup status.</p>
            @endif
        </div>

        <div class="content-card bg-white p-4 mb-4">
            <h2 class="h5 mb-3">Provisioning Overview</h2>
            <div class="row g-3 mb-3">
                <div class="col-4">
                    <div class="metric-card p-3 h-100">
                        <div class="fs-3 fw-bold">{{ $pendingCount }}</div>
                        <div class="text-muted small">Pending</div>
                    </div>
                </div>
                <div class="col-4">
                    <div class="metric-card p-3 h-100">
                        <div class="fs-3 fw-bold">{{ $provisionedCount }}</div>
                        <div class="text-muted small">Provisioned</div>
                    </div>
                </div>
                <div class="col-4">
                    <div class="metric-card p-3 h-100">
                        <div class="fs-3 fw-bold">{{ $failedCount }}</div>
                        <div class="text-muted small">Failed</div>
                    </div>
                </div>
            </div>

            @if ($latestDeployment)
                <dl class="row mb-0">
                    <dt class="col-sm-5">Last Batch</dt>
                    <dd class="col-sm-7 text-break">{{ $latestDeployment->deployment_batch_reference }}</dd>
                    <dt class="col-sm-5">Status</dt>
                    <dd class="col-sm-7 text-capitalize">{{ $latestDeployment->status }}</dd>
                    <dt class="col-sm-5">Deployed At</dt>
                    <dd class="col-sm-7">{{ $latestDeployment->deployed_at?->format('Y-m-d H:i') ?? 'N/A' }}</dd>
                    <dt class="col-sm-5">Server</dt>
                    <dd class="col-sm-7 text-break">{{ $latestDeployment->backup_server ?: 'N/A' }}</dd>
                    <dt class="col-sm-5">Path</dt>
                    <dd class="col-sm-7 font-monospace small text-break">{{ $latestDeployment->authorized_keys_path ?: 'N/A' }}</dd>
                </dl>

                @if ($latestDeployment->error_message)
                    <div class="alert alert-danger rounded-4 mt-3 mb-0">{{ $latestDeployment->error_message }}</div>
                @endif
            @else
                <p class="text-muted mb-0">No authorized_keys deployment has been recorded yet.</p>
            @endif
        </div>
    </div>

    @if ($showKeyModal)
        <div class="modal fade show d-block" tabindex="-1" aria-modal="true" role="dialog" style="background: rgba(10, 28, 43, 0.45);">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content border-0 rounded-4">
                    <div class="modal-header border-0 px-4 pt-4">
                        <div>
                            <h2 class="modal-title h4 mb-1">{{ $selectedConfiguration ? 'Update Backup Configuration' : 'Add Backup Configuration' }}</h2>
                            <p class="text-muted mb-0">Save the facility backup settings, provision the central directory, and generate the facility backup script.</p>
                        </div>
                        <button type="button" class="btn-close" wire:click="closeKeyModal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body px-4 pb-4">
                        @if ($flashError)
                            <div class="alert alert-danger rounded-4 mb-3">{{ $flashError }}</div>
                        @endif

                        @if ($errors->any())
                            <div class="alert alert-danger rounded-4 mb-3">
                                {{ $errors->first() }}
                            </div>
                        @endif

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Region <span class="text-danger">*</span></label>
                                <select class="form-select" wire:model.live="regionId">
                                    <option value="">Select region</option>
                                    @foreach ($regions as $region)
                                        <option value="{{ $region->id }}">{{ $region->name }}</option>
                                    @endforeach
                                </select>
                                <div class="form-text">Used only to narrow the facility list.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">District <span class="text-danger">*</span></label>
                                <select class="form-select" wire:model.live="districtName" @disabled($regionId === '')>
                                    <option value="">Select district</option>
                                    @foreach ($districtOptions as $district)
                                        <option value="{{ $district }}">{{ $district }}</option>
                                    @endforeach
                                </select>
                                <div class="form-text">
                                    {{ $regionId !== '' ? 'District options are filtered by the selected region.' : 'Choose a region first.' }}
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Facility <span class="text-danger">*</span></label>
                                <select class="form-select" wire:model.live="facilityId" @disabled($districtName === '')>
                                    <option value="">Select facility</option>
                                    @foreach ($facilities as $facility)
                                        <option value="{{ $facility->id }}">
                                            {{ $facility->name }}{{ $facility->code ? ' | '.$facility->code : '' }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-text">
                                    {{ $districtName !== '' ? 'Facilities are limited to the selected district.' : 'Choose a district first.' }}
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Backup Directory Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control font-monospace" value="{{ $backupDirectoryName }}" readonly>
                                <div class="form-text">Generated automatically from the selected facility and validated again on save.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">A-LIS Database Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" wire:model="databaseName" placeholder="alis_db">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">A-LIS Database Username <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" wire:model="databaseUsername" placeholder="alis_user">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">A-LIS Database Password <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" wire:model="databasePassword" placeholder="{{ $selectedConfiguration ? 'Leave blank to preserve the current password' : 'Enter database password' }}">
                                @if ($selectedConfiguration)
                                    <div class="form-text">Leave this blank to keep the existing saved password.</div>
                                @endif
                            </div>
                            <div class="col-12">
                                <label class="form-label">SSH Public Key <span class="text-danger">*</span></label>
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
                                <p class="mb-3">Provide a reason only if you are changing the SSH public key.</p>
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
                        <button type="button" class="btn btn-dark rounded-pill px-4" wire:click="saveConfiguration" wire:loading.attr="disabled">Save Configuration</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
