@extends('layouts.app', ['title' => 'Public Dashboard'])

@section('content')
    <style>
        .public-dashboard-shell {
            position: relative;
            isolation: isolate;
            overflow: hidden;
            border-radius: 2rem;
            padding: 1.5rem;
        }

        .public-dashboard-shell::before {
            content: "";
            position: absolute;
            inset: 0;
            background: url('/unhls33.jpg') center 38% / cover no-repeat;
            filter: blur(2px) saturate(0.96) brightness(1.04) contrast(1.02);
            transform: scale(1.02);
            z-index: -2;
        }

        .public-dashboard-shell::after {
            content: "";
            position: absolute;
            inset: 0;
            background:
                linear-gradient(180deg, rgba(251, 252, 254, 0.92) 0%, rgba(246, 243, 235, 0.94) 100%),
                radial-gradient(circle at top right, rgba(13, 148, 136, 0.16), transparent 24%);
            z-index: -1;
        }

        .metric-card-button {
            border: 0;
            width: 100%;
            text-align: left;
            padding: 0;
            background: transparent;
            transition: transform 0.18s ease, box-shadow 0.18s ease;
            cursor: pointer;
        }

        .metric-card-button:hover,
        .metric-card-button:focus-visible {
            transform: translateY(-3px);
        }

        .metric-card-button:focus-visible .metric-card {
            box-shadow: 0 0 0 0.25rem rgba(18, 59, 93, 0.14);
        }

        .metric-card-button .metric-card {
            transition: box-shadow 0.18s ease, border-color 0.18s ease;
        }

        .metric-card-button:hover .metric-card {
            border-color: rgba(18, 59, 93, 0.18);
            box-shadow: 0 18px 40px rgba(18, 59, 93, 0.12);
        }

        .metric-card-hint {
            font-size: 0.78rem;
            color: #5c6c7b;
        }

        .filter-note {
            font-size: 0.84rem;
            color: #5c6c7b;
        }

        @media (max-width: 991.98px) {
            .public-dashboard-shell {
                border-radius: 1.5rem;
                padding: 1rem;
            }

            .public-dashboard-shell::before {
                background-position: center center;
            }
        }
    </style>

    <div class="public-dashboard-shell">
        <div class="content-card bg-white p-4 mb-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                <div>
                    <h2 class="h4 mb-1">Today's Snapshot</h2>
                    <p class="text-muted mb-0">{{ $selectedDate }}</p>
                </div>
                <span class="stats-badge stats-badge--teal">Daily Stats</span>
            </div>

            <div class="row g-3">
                @foreach ([
                    ['label' => 'Tickets Logged Today', 'value' => $todayStats['total'], 'badge' => 'Volume', 'class' => 'stats-badge--navy', 'accent' => '#123b5d'],
                    ['label' => 'Opened Today', 'value' => $todayStats['open'], 'badge' => 'In Progress', 'class' => 'stats-badge--teal', 'accent' => '#0d9488'],
                    ['label' => 'Resolved Today', 'value' => $todayStats['resolved'], 'badge' => 'Resolved', 'class' => 'stats-badge--orange', 'accent' => '#f28c28'],
                    ['label' => 'Closed Today', 'value' => $todayStats['closed'], 'badge' => 'Completed', 'class' => 'stats-badge--plum', 'accent' => '#7b3f6c'],
                    ['label' => 'Facilities Reporting Today', 'value' => $todayStats['facilities'], 'badge' => 'Coverage', 'class' => 'stats-badge--gold', 'accent' => '#be8c2a'],
                ] as $item)
                    <div class="col-md-6 col-xl">
                        <div class="metric-card metric-card-accent p-3 h-100" style="--metric-accent: {{ $item['accent'] }};">
                            <span class="stats-badge {{ $item['class'] }} mb-2">{{ $item['badge'] }}</span>
                            <div class="text-muted small">{{ $item['label'] }}</div>
                            <div class="fs-3 fw-bold">{{ $item['value'] }}</div>
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
                <a class="btn btn-outline-secondary rounded-pill px-4" href="{{ route('dashboard.public') }}">Reset Filters</a>
            </div>

            <form method="GET" action="{{ route('dashboard.public') }}" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Date Filter</label>
                    <select name="period" class="form-select">
                        @foreach ($periodOptions as $value => $label)
                            <option value="{{ $value }}" @selected(request('period', 'all_time') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Facility</label>
                    <select name="facility_id" class="form-select">
                        <option value="">All facilities</option>
                        @foreach ($facilities as $facility)
                            <option value="{{ $facility->id }}" @selected((string) request('facility_id') === (string) $facility->id)>{{ $facility->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Facility Kind</label>
                    <select name="facility_type" class="form-select">
                        <option value="">All facility kinds</option>
                        @foreach ($facilityTypes as $facilityType)
                            <option value="{{ $facilityType }}" @selected(request('facility_type') === $facilityType)>{{ $facilityType }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">System</label>
                    <select name="system_id" class="form-select">
                        <option value="">All systems</option>
                        @foreach ($systems as $system)
                            <option value="{{ $system->id }}" @selected((string) request('system_id') === (string) $system->id)>{{ $system->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Start Date</label>
                    <input type="date" name="start_date" class="form-control" value="{{ request('start_date') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">End Date</label>
                    <input type="date" name="end_date" class="form-control" value="{{ request('end_date') }}">
                </div>
                <div class="col-12">
                    <div class="filter-note">Use the custom date fields when the date filter is set to `Custom Date Range`.</div>
                </div>
                <div class="col-12 d-flex justify-content-end">
                    <button class="btn btn-dark rounded-pill px-4">Apply Filters</button>
                </div>
            </form>
        </div>

        <div class="row g-3 mb-4">
            @foreach ($metricCards as $card)
                <div class="col-md-4 col-xl">
                    <button
                        type="button"
                        class="metric-card-button"
                        data-bs-toggle="modal"
                        data-bs-target="#metricModal-{{ $card['key'] }}"
                        title="{{ $card['hover_text'] }}"
                    >
                        <div
                            class="metric-card metric-card-accent p-3 h-100"
                            style="--metric-accent: {{ match($card['key']) {
                                'total_tickets' => '#123b5d',
                                'open_tickets' => '#0d9488',
                                'resolved_tickets' => '#f28c28',
                                'closed_tickets' => '#7b3f6c',
                                'average_resolution_hours' => '#52606d',
                                default => '#be8c2a',
                            } }};"
                        >
                            <span class="stats-badge {{ match($card['key']) {
                                'total_tickets' => 'stats-badge--navy',
                                'open_tickets' => 'stats-badge--teal',
                                'resolved_tickets' => 'stats-badge--orange',
                                'closed_tickets' => 'stats-badge--plum',
                                'average_resolution_hours' => 'stats-badge--slate',
                                default => 'stats-badge--gold',
                            } }} mb-2">
                                {{ match($card['key']) {
                                    'total_tickets' => 'All Cases',
                                    'open_tickets' => 'Backlog',
                                    'resolved_tickets' => 'Turnaround',
                                    'closed_tickets' => 'Completed',
                                    'average_resolution_hours' => 'SLA Pace',
                                    default => 'Coverage',
                                } }}
                            </span>
                            <div class="text-muted small">{{ $card['label'] }}</div>
                            <div class="fs-3 fw-bold">{{ $card['value'] }}</div>
                            <div class="metric-card-hint mt-2">Click for more details</div>
                        </div>
                    </button>
                </div>
            @endforeach
        </div>

        <div class="row g-4">
            <div class="col-lg-6">
                <div class="content-card bg-white p-4">
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
                <div class="content-card bg-white p-4">
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
                <div class="content-card bg-white p-4">
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
                <div class="content-card bg-white p-4">
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
        <div class="modal fade" id="metricModal-{{ $card['key'] }}" tabindex="-1" aria-labelledby="metricModalLabel-{{ $card['key'] }}" aria-hidden="true">
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
@endsection

@push('scripts')
    <script>
        document.querySelectorAll('.metric-card-button').forEach((element) => {
            new bootstrap.Tooltip(element);
        });
    </script>
@endpush
