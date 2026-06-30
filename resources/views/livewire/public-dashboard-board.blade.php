<div wire:loading.class="opacity-75">
    <div class="public-dashboard-shell">
        <div class="content-card bg-white p-4 mb-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                <div>
                    <h2 class="h4 mb-1">Today's Snapshot</h2>
                    <p class="text-muted mb-0">{{ $selectedDate }}</p>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="stats-badge stats-badge--teal">Daily Stats</span>
                    <span class="small text-muted" wire:loading.delay>Updating...</span>
                </div>
            </div>

            <div class="row g-3">
                @foreach ([
                    'Tickets Logged Today' => $todayStats['total'],
                    'Opened Today' => $todayStats['open'],
                    'Resolved Today' => $todayStats['resolved'],
                    'Closed Today' => $todayStats['closed'],
                    'Facilities Reporting Today' => $todayStats['facilities'],
                ] as $label => $value)
                    <div class="col-md-6 col-xl">
                        <div class="metric-card p-3 h-100">
                            <div class="text-muted small">{{ $label }}</div>
                            <div class="fs-3 fw-bold">{{ $value }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="content-card bg-white p-4 mb-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                <div>
                    <h2 class="h4 mb-1">Filterable Performance View</h2>
                    <p class="text-muted mb-0">Review ticket activity by date range, facility kind, individual facility, and system.</p>
                    <div class="filter-note mt-1">Current range: {{ $filteredDateLabel }}</div>
                </div>
                <button type="button" class="btn btn-outline-secondary rounded-pill px-4" wire:click="resetFilters">Reset Filters</button>
            </div>

            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Date Filter</label>
                    <select wire:model.live="period" class="form-select">
                        @foreach ($periodOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Facility</label>
                    <select wire:model.live="facilityId" class="form-select">
                        <option value="">All facilities</option>
                        @foreach ($facilities as $facility)
                            <option value="{{ $facility->id }}">{{ $facility->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Facility Kind</label>
                    <select wire:model.live="facilityType" class="form-select">
                        <option value="">All facility kinds</option>
                        @foreach ($facilityTypes as $facilityType)
                            <option value="{{ $facilityType }}">{{ $facilityType }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">System</label>
                    <select wire:model.live="systemId" class="form-select">
                        <option value="">All systems</option>
                        @foreach ($systems as $system)
                            <option value="{{ $system->id }}">{{ $system->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Start Date</label>
                    <input type="date" wire:model.live="startDate" class="form-control" @disabled($period !== 'custom')>
                </div>
                <div class="col-md-3">
                    <label class="form-label">End Date</label>
                    <input type="date" wire:model.live="endDate" class="form-control" @disabled($period !== 'custom')>
                </div>
                <div class="col-12">
                    <div class="filter-note">Use the custom date fields when the date filter is set to `Custom Date Range`.</div>
                </div>
            </div>
        </div>

        <div class="content-card bg-white p-4 mb-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                <div>
                    <h2 class="h4 mb-1">Overall Performance</h2>
                    <p class="text-muted mb-0">Tap any statistic to open the detailed breakdown.</p>
                </div>
                <span class="stats-badge stats-badge--navy">All-Time Stats</span>
            </div>

            <div class="row g-3">
                @foreach ($metricCards as $card)
                    <div class="col-md-4 col-xl">
                        <button
                            type="button"
                            class="metric-card-button"
                            data-bs-toggle="modal"
                            data-bs-target="#metricModal-{{ $card['key'] }}"
                            title="{{ $card['hover_text'] }}"
                        >
                            <div class="metric-card p-3 h-100">
                                <div class="text-muted small">{{ $card['label'] }}</div>
                                <div class="fs-3 fw-bold">{{ $card['value'] }}</div>
                                <div class="metric-card-hint mt-2">Click for more details</div>
                            </div>
                        </button>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-6">
                <div class="content-card bg-white p-4 scheduler-border">
                    <h2 class="h5">Tickets by System</h2>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            @forelse ($bySystem as $row)
                                <tr><td>{{ $row->name ?? 'Unspecified' }}</td><td class="text-end">{{ $row->total }}</td></tr>
                            @empty
                                <tr><td colspan="2" class="text-center text-muted">No matching records found.</td></tr>
                            @endforelse
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="content-card bg-white p-4 scheduler-border">
                    <h2 class="h5">Tickets by Region</h2>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            @forelse ($byRegion as $row)
                                <tr><td>{{ $row->name ?? 'Unspecified' }}</td><td class="text-end">{{ $row->total }}</td></tr>
                            @empty
                                <tr><td colspan="2" class="text-center text-muted">No matching records found.</td></tr>
                            @endforelse
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="content-card bg-white p-4 scheduler-border">
                    <h2 class="h5">Top Facilities by Ticket Volume</h2>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            @forelse ($byFacility as $row)
                                <tr><td>{{ $row->name ?? 'Unspecified' }}</td><td class="text-end">{{ $row->total }}</td></tr>
                            @empty
                                <tr><td colspan="2" class="text-center text-muted">No matching records found.</td></tr>
                            @endforelse
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="content-card bg-white p-4 scheduler-border">
                    <h2 class="h5">Most Reported Issues</h2>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            @forelse ($commonIssues as $row)
                                <tr><td>{{ $row->name }}</td><td class="text-end">{{ $row->total }}</td></tr>
                            @empty
                                <tr><td colspan="2" class="text-center text-muted">No matching records found.</td></tr>
                            @endforelse
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-12">
                <div class="content-card bg-white p-4">
                    <h2 class="h5">Repeat Issues</h2>
                    <div class="table-responsive">
                        <table class="table">
                            <thead><tr><th>Facility</th><th>Issue</th><th class="text-end">Occurrences</th></tr></thead>
                            <tbody>
                                @forelse ($repeatIssues as $row)
                                    <tr>
                                        <td>{{ $row->facility_name ?? 'Unspecified' }}</td>
                                        <td>{{ $row->issue_name }}</td>
                                        <td class="text-end">{{ $row->total }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="text-center text-muted">No repeat issues identified for the selected filters.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @foreach ($metricCards as $card)
        <div class="modal fade" id="metricModal-{{ $card['key'] }}" tabindex="-1" aria-labelledby="metricModalLabel-{{ $card['key'] }}" aria-hidden="true" wire:ignore.self>
            <div class="modal-dialog modal-xl modal-dialog-scrollable">
                <div class="modal-content border-0 rounded-4">
                    <div class="modal-header">
                        <div>
                            <h2 class="modal-title fs-5" id="metricModalLabel-{{ $card['key'] }}">{{ $card['modal_title'] }}</h2>
                            <p class="text-muted mb-0">{{ $card['modal_description'] }}</p>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        @if ($card['type'] === 'tickets')
                            <div class="table-responsive">
                                <table class="table align-middle">
                                    <thead>
                                        <tr>
                                            <th>Ticket</th>
                                            <th>System</th>
                                            <th>Facility</th>
                                            <th>Facility Kind</th>
                                            <th>Status</th>
                                            <th>Assigned To</th>
                                            <th>Reported</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($card['rows'] as $row)
                                            <tr>
                                                <td>{{ $row['ticket_number'] }}</td>
                                                <td>{{ $row['system'] }}</td>
                                                <td>{{ $row['facility'] }}</td>
                                                <td>{{ $row['facility_type'] }}</td>
                                                <td>{{ $row['status'] }}</td>
                                                <td>{{ $row['assigned_to'] }}</td>
                                                <td>{{ $row['reported_at'] }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="7" class="text-center text-muted">No matching tickets found for this metric.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="table-responsive">
                                <table class="table align-middle">
                                    <thead>
                                        <tr>
                                            <th>Facility</th>
                                            <th>Facility Kind</th>
                                            <th>Region</th>
                                            <th class="text-end">Tickets Logged</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($card['rows'] as $row)
                                            <tr>
                                                <td>{{ $row['facility'] }}</td>
                                                <td>{{ $row['facility_type'] }}</td>
                                                <td>{{ $row['region'] }}</td>
                                                <td class="text-end">{{ $row['total'] }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="4" class="text-center text-muted">No facilities found for this metric.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                    <div class="modal-footer">
                        <small class="text-muted me-auto">Showing up to the latest 20 matching records for the current filter selection.</small>
                        <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    @endforeach
</div>
