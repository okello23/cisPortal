<div wire:loading.class="opacity-90">
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
                    <!-- <p class="text-muted mb-0">Manage facility database backup settings, central server provisioning, and script downloads in one place.</p> -->
                </div>
                <div class="d-flex gap-2">
                    <a
                        href="{{ asset('docs/A-LIS%20Backup%20Restoration%20SOP.pdf') }}"
                        class="btn btn-outline-dark rounded-pill px-4"
                        download
                        target="_blank"
                        rel="noopener"
                    >
                        Download SOP PDF
                    </a>
                    <button type="button" class="btn btn-dark rounded-pill px-4" wire:click="openCreateModal">
                        Add Backup Configuration
                    </button>
                </div>
            </div>


            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="metric-card p-3 h-100">
                        <div class="fs-3 fw-bold">{{ $pendingCount }}</div>
                        <div class="text-muted small">Pending</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="metric-card p-3 h-100">
                        <div class="fs-3 fw-bold">{{ $provisionedCount }}</div>
                        <div class="text-muted small">Provisioned</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="metric-card p-3 h-100">
                        <div class="fs-3 fw-bold">{{ $failedCount }}</div>
                        <div class="text-muted small">Failed</div>
                    </div>
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

            <div class="table-responsive border rounded-4 overflow-hidden">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Facility</th>
                            <th>Added By</th>
                            <th>Date Added</th>
                            <th>Backup Directory</th>
                            <th>Last Backup</th>
                            <th>Status</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($configurations as $configuration)
                            <tr>
                                <td>
                                    <button
                                        type="button"
                                        class="btn btn-link p-0 text-decoration-none text-start fw-semibold"
                                        wire:click="openFacilityDetails({{ $configuration->facility_id }})"
                                    >
                                        {{ $configuration->facility->name }}
                                    </button>
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
                                <td>{{ $configuration->creator?->name ?? 'System' }}</td>
                                <td>{{ $configuration->created_at?->format('Y-m-d H:i') ?? 'N/A' }}</td>
                                <td class="font-monospace small">{{ $configuration->backup_directory_name }}</td>
                                <td>
                                    @php
                                        $lastBackup = $lastBackups[$configuration->backup_directory_name] ?? null;
                                    @endphp
                                    @if ($lastBackups === null)
                                        <span class="text-muted">Unavailable</span>
                                    @elseif ($lastBackup)
                                        <div class="fw-semibold">{{ $lastBackup->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</div>
                                        <div class="small text-muted">{{ $lastBackup->diffForHumans() }}</div>
                                    @else
                                        <span class="text-muted">No backup found</span>
                                    @endif
                                </td>
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
                                <td class="text-end">
                                    <div class="d-flex justify-content-end gap-2 flex-wrap">
                                        @if ($configuration->status === \App\Models\AlisBackupConfiguration::STATUS_PROVISIONED)
                                            @if (\Illuminate\Support\Facades\Route::has('infrastructure.alis-remote-backup-keys.download-script'))
                                                <a href="{{ route('infrastructure.alis-remote-backup-keys.download-script', $configuration) }}" class="btn btn-sm btn-outline-success rounded-pill d-inline-flex align-items-center gap-2">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                                                        <path d="M.5 9.9a.5.5 0 0 1 .5.5v2.6a.5.5 0 0 0 .5.5h13a.5.5 0 0 0 .5-.5v-2.6a.5.5 0 0 1 1 0v2.6A1.5 1.5 0 0 1 14.5 14h-13A1.5 1.5 0 0 1 0 12.5v-2.6a.5.5 0 0 1 .5-.5Z"/>
                                                        <path d="M5.646 8.854a.5.5 0 0 1 .708 0L7.5 10.293V2.5a.5.5 0 0 1 1 0v7.793l1.146-1.439a.5.5 0 1 1 .708.708l-2 2.5a.5.5 0 0 1-.708 0l-2-2.5a.5.5 0 0 1 0-.708Z"/>
                                                    </svg>
                                                    <span>Download</span>
                                                </a>
                                            @else
                                                <button type="button" class="btn btn-sm btn-outline-success rounded-pill d-inline-flex align-items-center gap-2" disabled>
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                                                        <path d="M.5 9.9a.5.5 0 0 1 .5.5v2.6a.5.5 0 0 0 .5.5h13a.5.5 0 0 0 .5-.5v-2.6a.5.5 0 0 1 1 0v2.6A1.5 1.5 0 0 1 14.5 14h-13A1.5 1.5 0 0 1 0 12.5v-2.6a.5.5 0 0 1 .5-.5Z"/>
                                                        <path d="M5.646 8.854a.5.5 0 0 1 .708 0L7.5 10.293V2.5a.5.5 0 0 1 1 0v7.793l1.146-1.439a.5.5 0 1 1 .708.708l-2 2.5a.5.5 0 0 1-.708 0l-2-2.5a.5.5 0 0 1 0-.708Z"/>
                                                    </svg>
                                                    <span>Download</span>
                                                </button>
                                            @endif
                                        @endif
                                        <button type="button" class="btn btn-sm btn-outline-dark rounded-pill d-inline-flex align-items-center gap-2" wire:click="openEditModal({{ $configuration->facility_id }})">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                                                <path d="M12.854.146a.5.5 0 0 1 .707 0l2.586 2.586a.5.5 0 0 1 0 .707l-9.5 9.5L4 13l.061-2.646 9.5-9.5ZM11.207 2.5 5.06 8.646l-.04 1.768 1.768-.04L12.914 4.25 11.207 2.5Z"/>
                                                <path fill-rule="evenodd" d="M1 13.5A1.5 1.5 0 0 0 2.5 15h11A1.5 1.5 0 0 0 15 13.5V8a.5.5 0 0 0-1 0v5.5a.5.5 0 0 1-.5.5h-11a.5.5 0 0 1-.5-.5v-11a.5.5 0 0 1 .5-.5H8a.5.5 0 0 0 0-1H2.5A1.5 1.5 0 0 0 1 2.5v11Z"/>
                                            </svg>
                                            <span>Update</span>
                                        </button>
                                        @if ($configuration->status === \App\Models\AlisBackupConfiguration::STATUS_PROVISIONING_FAILED || $configuration->status === \App\Models\AlisBackupConfiguration::STATUS_PENDING_PROVISIONING)
                                            <button type="button" class="btn btn-sm btn-outline-warning rounded-pill" wire:click="retryProvisioning({{ $configuration->id }})">
                                                Retry
                                            </button>
                                        @endif
                                        <button
                                            type="button"
                                            class="btn btn-sm rounded-pill d-inline-flex align-items-center gap-2 {{ $configuration->status === \App\Models\AlisBackupConfiguration::STATUS_DISABLED ? 'btn-outline-secondary' : 'btn-outline-danger' }}"
                                            wire:click="{{ $configuration->status === \App\Models\AlisBackupConfiguration::STATUS_DISABLED ? 'activateConfiguration('.$configuration->id.')' : 'confirmDeactivation('.$configuration->id.')' }}"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                                                @if ($configuration->status === \App\Models\AlisBackupConfiguration::STATUS_DISABLED)
                                                    <path d="M8 3a5 5 0 1 0 4.546 2.916.5.5 0 1 1 .908-.418A6 6 0 1 1 8 2v1Z"/>
                                                    <path d="M8 0a.5.5 0 0 1 .5.5V4a.5.5 0 0 1-1 0V.5A.5.5 0 0 1 8 0Z"/>
                                                @else
                                                    <path d="M8 15A7 7 0 1 1 15 8 7.008 7.008 0 0 1 8 15Zm0-13A6 6 0 1 0 14 8 6.007 6.007 0 0 0 8 2Z"/>
                                                    <path d="M4.646 4.646a.5.5 0 0 1 .708 0L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 0 1 0-.708Z"/>
                                                @endif
                                            </svg>
                                            <span>{{ $configuration->status === \App\Models\AlisBackupConfiguration::STATUS_DISABLED ? 'Activate' : 'Deactivate' }}</span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">No backup configurations have been saved yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    @if ($showFacilityDetailsModal)
        <div class="modal fade show d-block" tabindex="-1" aria-modal="true" role="dialog" style="background: rgba(10, 28, 43, 0.45);">
            <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
                <div class="modal-content border-0 rounded-4">
                    <div class="modal-header border-0 px-4 pt-4">
                        <div>
                            <h2 class="modal-title h4 mb-1">{{ $selectedFacility?->name ?? 'Facility Details' }}</h2>
                            <p class="text-muted mb-0">Provision overview, current backup status, and the latest audit trail for this facility.</p>
                        </div>
                        <button type="button" class="btn-close" wire:click="closeFacilityDetailsModal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body px-4 pb-4">
                        @if ($selectedFacility && $selectedConfiguration)
                            <div class="row g-4">
                                <div class="col-lg-5">
                                    <div class="metric-card p-4 h-100">
                                        <div class="small text-uppercase text-muted fw-semibold mb-2">Provision Overview</div>
                                        <div class="fw-semibold mb-1">{{ $selectedFacility->name }}</div>
                                        <div class="small text-muted mb-3">
                                            {{ $selectedFacility->district_name ?: 'No district' }}
                                            @if ($selectedFacility->region?->name)
                                                | {{ $selectedFacility->region->name }}
                                            @endif
                                            @if ($selectedFacility->code)
                                                | {{ $selectedFacility->code }}
                                            @endif
                                        </div>

                                        <dl class="row mb-0">
                                            <dt class="col-sm-5">Added By</dt>
                                            <dd class="col-sm-7">{{ $selectedConfiguration->creator?->name ?? 'System' }}</dd>
                                            <dt class="col-sm-5">Date Added</dt>
                                            <dd class="col-sm-7">{{ $selectedConfiguration->created_at?->format('Y-m-d H:i') ?? 'N/A' }}</dd>
                                            <dt class="col-sm-5">Backup Directory</dt>
                                            <dd class="col-sm-7 font-monospace small text-break">{{ $selectedConfiguration->backup_directory_name }}</dd>
                                            <dt class="col-sm-5">Database Name</dt>
                                            <dd class="col-sm-7">{{ $selectedConfiguration->database_name }}</dd>
                                            <dt class="col-sm-5">DB Username</dt>
                                            <dd class="col-sm-7">{{ $selectedConfiguration->database_username }}</dd>
                                            <dt class="col-sm-5">Provisioned At</dt>
                                            <dd class="col-sm-7">{{ $selectedConfiguration->provisioned_at?->format('Y-m-d H:i') ?? 'N/A' }}</dd>
                                        </dl>
                                    </div>
                                </div>

                                <div class="col-lg-7">
                                    <div class="metric-card p-4 h-100">
                                        <div class="small text-uppercase text-muted fw-semibold mb-3">Current Backup Status</div>
                                        <div class="row g-3 mb-3">
                                            <div class="col-md-6">
                                                <div class="border rounded-4 p-3 h-100 bg-white">
                                                    <div class="small text-muted mb-2">Configuration Status</div>
                                                    @php
                                                        $modalBadgeClass = match ($selectedConfiguration->status) {
                                                            \App\Models\AlisBackupConfiguration::STATUS_PROVISIONED => 'stats-badge--teal',
                                                            \App\Models\AlisBackupConfiguration::STATUS_PROVISIONING_FAILED => 'stats-badge--red',
                                                            \App\Models\AlisBackupConfiguration::STATUS_DISABLED => 'stats-badge--slate',
                                                            default => 'stats-badge--orange',
                                                        };
                                                    @endphp
                                                    <span class="stats-badge {{ $modalBadgeClass }}">{{ str_replace('_', ' ', ucfirst($selectedConfiguration->status)) }}</span>
                                                    <div class="small text-muted mt-2">Last updated {{ $selectedConfiguration->updated_at?->format('Y-m-d H:i') ?? 'N/A' }}</div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="border rounded-4 p-3 h-100 bg-white">
                                                    <div class="small text-muted mb-2">Key Deployment Status</div>
                                                    @if ($selectedKey)
                                                        <div class="fw-semibold text-capitalize">{{ $selectedKey->deployment_status }}</div>
                                                        <div class="small text-muted mt-2">Key status: {{ $selectedKey->status }}</div>
                                                        <div class="small text-muted">Deployed at: {{ $selectedKey->deployed_at?->format('Y-m-d H:i') ?? 'N/A' }}</div>
                                                    @else
                                                        <div class="text-muted">No key deployment record yet.</div>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>

                                        @if ($selectedConfiguration->last_provisioning_error)
                                            <div class="alert alert-danger rounded-4 mb-3">{{ $selectedConfiguration->last_provisioning_error }}</div>
                                        @endif

                                        @if ($selectedKey?->last_deployment_error)
                                            <div class="alert alert-warning rounded-4 mb-0">{{ $selectedKey->last_deployment_error }}</div>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <div class="content-card bg-white p-4 mt-4">
                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                                    <h3 class="h5 mb-0">Audit Trail</h3>
                                    <span class="small text-muted">Most recent 15 events</span>
                                </div>
                                <div class="table-responsive">
                                    <table class="table align-middle mb-0">
                                        <thead>
                                            <tr>
                                                <th>When</th>
                                                <th>Action</th>
                                                <th>Reason / Comments</th>
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
                                                    <td colspan="5" class="text-center text-muted py-4">No audit trail entries have been recorded for this facility yet.</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @else
                            <p class="text-muted mb-0">No provision details are available for this facility.</p>
                        @endif
                    </div>
                    <div class="modal-footer border-0 px-4 pb-4">
                        <button type="button" class="btn btn-outline-secondary rounded-pill px-4" wire:click="closeFacilityDetailsModal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if ($confirmingDeactivationConfigurationId)
        <div class="modal fade show d-block" tabindex="-1" aria-modal="true" role="dialog" style="background: rgba(10, 28, 43, 0.45);">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 rounded-4">
                    <div class="modal-header border-0 px-4 pt-4">
                        <div>
                            <h2 class="modal-title h5 mb-1">Confirm Key Deactivation</h2>
                            <p class="text-muted mb-0">This will disable the facility backup configuration and remove the active backup key from the next deployment.</p>
                        </div>
                        <button type="button" class="btn-close" wire:click="cancelDeactivation" aria-label="Close"></button>
                    </div>
                    <div class="modal-body px-4 pb-3">
                        <p class="mb-0">Are you sure you want to deactivate this key?</p>
                    </div>
                    <div class="modal-footer border-0 px-4 pb-4">
                        <button type="button" class="btn btn-outline-secondary rounded-pill px-4" wire:click="cancelDeactivation">Cancel</button>
                        <button type="button" class="btn btn-outline-danger rounded-pill px-4" wire:click="deactivateConfirmedConfiguration">Deactivate</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

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

                        <div class="alert alert-light border rounded-4 d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                            <div>
                                <div class="fw-semibold">Backup Restoration SOP</div>
                                <div class="small text-muted">Download the Linux Ubuntu off-site database backup and restoration SOP for step-by-step guidance.</div>
                            </div>
                            <a
                                href="{{ asset('docs/A-LIS%20Backup%20Restoration%20SOP.pdf') }}"
                                class="btn btn-outline-dark rounded-pill px-4"
                                download
                                target="_blank"
                                rel="noopener"
                            >
                                Download SOP
                            </a>
                        </div>

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
