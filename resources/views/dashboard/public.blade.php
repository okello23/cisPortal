@extends('layouts.app', ['title' => 'Public Dashboard'])

@push('styles')
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

        .stats-strip {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.25rem 1.5rem;
            padding: 1rem 0 0.25rem;
        }

        .stats-strip-button {
            border: 0;
            background: transparent;
            padding: 0;
            width: 100%;
            text-align: left;
        }

        .stats-strip-button:focus-visible {
            outline: 0;
        }

        .stats-strip-button:focus-visible .stat-tile,
        .stats-strip-button:hover .stat-tile {
            transform: translateY(-2px);
        }

        .stat-tile {
            display: flex;
            align-items: center;
            gap: 0.9rem;
            min-height: 4.75rem;
            transition: transform 0.18s ease;
        }

        .stat-icon-box {
            width: 3rem;
            height: 3rem;
            border-radius: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            flex: 0 0 auto;
        }

        .stat-copy {
            min-width: 0;
        }

        .stat-value {
            color: #123b5d;
            font-size: 1.9rem;
            font-weight: 700;
            line-height: 1;
            margin-bottom: 0.25rem;
        }

        .stat-label {
            color: #0f4c8a;
            font-size: 0.88rem;
            font-weight: 500;
            line-height: 1.35;
        }

        .stat-icon-box svg {
            width: 1.55rem;
            height: 1.55rem;
            stroke: currentColor;
        }

        .stat-icon-box--navy { background: #118ab2; }
        .stat-icon-box--teal { background: #29b765; }
        .stat-icon-box--orange { background: #f08a24; }
        .stat-icon-box--plum { background: #b31e49; }
        .stat-icon-box--gold { background: #ea8a1f; }
        .stat-icon-box--rose { background: #bf1f47; }

        @media (max-width: 991.98px) {
            .public-dashboard-shell {
                border-radius: 1.5rem;
                padding: 1rem;
            }

            .public-dashboard-shell::before {
                background-position: center center;
            }

            .stats-strip {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
        }
    </style>
@endpush

@section('content')
    <div class="public-dashboard-shell">
        <div class="content-card bg-white p-4 mb-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                <div>
                    <h2 class="h4 mb-1">Today's Snapshot</h2>
                    <p class="text-muted mb-0">{{ $selectedDate }}</p>
                </div>
                <span class="stats-badge stats-badge--teal">Daily Stats</span>
            </div>

            <div class="stats-strip">
                @foreach ([
                    ['label' => 'Tickets Logged Today', 'value' => $todayStats['total'], 'icon_color' => 'navy', 'icon' => 'clipboard'],
                    ['label' => 'Opened Today', 'value' => $todayStats['open'], 'icon_color' => 'teal', 'icon' => 'check-circle'],
                    ['label' => 'Resolved Today', 'value' => $todayStats['resolved'], 'icon_color' => 'orange', 'icon' => 'pause-bars'],
                    ['label' => 'Closed Today', 'value' => $todayStats['closed'], 'icon_color' => 'plum', 'icon' => 'trash'],
                    ['label' => 'Facilities Reporting Today', 'value' => $todayStats['facilities'], 'icon_color' => 'gold', 'icon' => 'spinner-dots'],
                ] as $item)
                    <div class="stat-tile">
                        <div class="stat-icon-box stat-icon-box--{{ $item['icon_color'] }}" aria-hidden="true">
                            @if ($item['icon'] === 'clipboard')
                                <svg viewBox="0 0 24 24" fill="none" stroke-width="2">
                                    <path d="M9 3h6" />
                                    <path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2" />
                                    <path d="M9 3a1 1 0 0 0-1 1v2h8V4a1 1 0 0 0-1-1Z" />
                                    <path d="M9 11h6M9 15h6" />
                                </svg>
                            @elseif ($item['icon'] === 'check-circle')
                                <svg viewBox="0 0 24 24" fill="none" stroke-width="2.2">
                                    <circle cx="12" cy="12" r="9" />
                                    <path d="m8.5 12.5 2.4 2.4 4.8-5.3" />
                                </svg>
                            @elseif ($item['icon'] === 'pause-bars')
                                <svg viewBox="0 0 24 24" fill="currentColor" stroke="none">
                                    <rect x="6" y="4" width="4" height="16" rx="1" />
                                    <rect x="14" y="4" width="4" height="16" rx="1" />
                                </svg>
                            @elseif ($item['icon'] === 'trash')
                                <svg viewBox="0 0 24 24" fill="none" stroke-width="2">
                                    <path d="M4 7h16" />
                                    <path d="M9 3h6" />
                                    <path d="M7 7l1 13h8l1-13" />
                                    <path d="M10 11v6M14 11v6" />
                                </svg>
                            @elseif ($item['icon'] === 'spinner-dots')
                                <svg viewBox="0 0 24 24" fill="currentColor" stroke="none">
                                    <circle cx="12" cy="4" r="2" />
                                    <circle cx="18.5" cy="7.5" r="1.7" opacity="0.85" />
                                    <circle cx="20" cy="14" r="1.5" opacity="0.7" />
                                    <circle cx="16.5" cy="19.5" r="1.4" opacity="0.55" />
                                    <circle cx="9.5" cy="20" r="1.3" opacity="0.45" />
                                    <circle cx="5" cy="15.5" r="1.2" opacity="0.35" />
                                </svg>
                            @endif
                        </div>
                        <div class="stat-copy">
                            <div class="stat-value">{{ $item['value'] }}</div>
                            <div class="stat-label">{{ $item['label'] }}</div>
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

        <div class="content-card bg-white p-4 mb-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                <div>
                    <h2 class="h4 mb-1">Overall Performance</h2>
                    <p class="text-muted mb-0">Tap any statistic to open the detailed breakdown.</p>
                </div>
                <span class="stats-badge stats-badge--navy">All-Time Stats</span>
            </div>

            <div class="stats-strip">
            @foreach ($metricCards as $card)
                <button
                    type="button"
                    class="stats-strip-button"
                    data-bs-toggle="modal"
                    data-bs-target="#metricModal-{{ $card['key'] }}"
                    title="{{ $card['hover_text'] }}"
                >
                    <div class="stat-tile">
                        <div class="stat-icon-box stat-icon-box--{{ match($card['key']) {
                            'total_tickets' => 'navy',
                            'open_tickets' => 'teal',
                            'resolved_tickets' => 'orange',
                            'closed_tickets' => 'plum',
                            'average_resolution_hours' => 'gold',
                            default => 'rose',
                        } }}" aria-hidden="true">
                            @if ($card['key'] === 'total_tickets')
                                <svg viewBox="0 0 24 24" fill="none" stroke-width="2">
                                    <path d="M9 3h6" />
                                    <path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2" />
                                    <path d="M9 3a1 1 0 0 0-1 1v2h8V4a1 1 0 0 0-1-1Z" />
                                    <path d="M9 11h6M9 15h6" />
                                </svg>
                            @elseif ($card['key'] === 'open_tickets')
                                <svg viewBox="0 0 24 24" fill="none" stroke-width="2.2">
                                    <circle cx="12" cy="12" r="9" />
                                    <path d="m8.5 12.5 2.4 2.4 4.8-5.3" />
                                </svg>
                            @elseif ($card['key'] === 'resolved_tickets')
                                <svg viewBox="0 0 24 24" fill="currentColor" stroke="none">
                                    <rect x="6" y="4" width="4" height="16" rx="1" />
                                    <rect x="14" y="4" width="4" height="16" rx="1" />
                                </svg>
                            @elseif ($card['key'] === 'closed_tickets')
                                <svg viewBox="0 0 24 24" fill="none" stroke-width="2">
                                    <path d="M7 7l10 10M17 7 7 17" />
                                    <circle cx="12" cy="12" r="9" />
                                </svg>
                            @elseif ($card['key'] === 'average_resolution_hours')
                                <svg viewBox="0 0 24 24" fill="none" stroke-width="2">
                                    <circle cx="12" cy="12" r="9" />
                                    <path d="M12 7v5l3 2" />
                                </svg>
                            @else
                                <svg viewBox="0 0 24 24" fill="currentColor" stroke="none">
                                    <circle cx="12" cy="4" r="2" />
                                    <circle cx="18.5" cy="7.5" r="1.7" opacity="0.85" />
                                    <circle cx="20" cy="14" r="1.5" opacity="0.7" />
                                    <circle cx="16.5" cy="19.5" r="1.4" opacity="0.55" />
                                    <circle cx="9.5" cy="20" r="1.3" opacity="0.45" />
                                    <circle cx="5" cy="15.5" r="1.2" opacity="0.35" />
                                </svg>
                            @endif
                        </div>
                        <div class="stat-copy">
                            <div class="stat-value">{{ $card['value'] }}</div>
                            <div class="stat-label">{{ $card['label'] }}</div>
                            <div class="metric-card-hint mt-1">Click for more details</div>
                        </div>
                    </div>
                </button>
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
