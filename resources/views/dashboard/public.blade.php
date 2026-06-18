@extends('layouts.app', ['title' => 'Public Dashboard'])

@section('content')
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <p class="text-uppercase text-muted fw-semibold small mb-1">Public Transparency Dashboard</p>
            <h1 class="h2 mb-0">ICT support performance at a glance</h1>
        </div>
        <a class="btn btn-dark rounded-pill px-4" href="{{ route('tickets.create') }}">Log New Issue</a>
    </div>

    <div class="content-card bg-white p-4 mb-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <div>
                <h2 class="h4 mb-1">Today's Snapshot</h2>
                <p class="text-muted mb-0">{{ $selectedDate }}</p>
            </div>
            <span class="badge rounded-pill text-bg-light px-3 py-2">Daily Stats</span>
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
                <p class="text-muted mb-0">Review all-time or period-specific ticket activity by facility and system.</p>
            </div>
            <a class="btn btn-outline-secondary rounded-pill px-4" href="{{ route('dashboard.public') }}">Reset Filters</a>
        </div>

        <form method="GET" action="{{ route('dashboard.public') }}" class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Facility</label>
                <select name="facility_id" class="form-select">
                    <option value="">All facilities</option>
                    @foreach ($facilities as $facility)
                        <option value="{{ $facility->id }}" @selected((string) request('facility_id') === (string) $facility->id)>{{ $facility->name }}</option>
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
            <div class="col-md-4">
                <label class="form-label">Period</label>
                <select name="period" class="form-select">
                    @foreach ($periodOptions as $value => $label)
                        <option value="{{ $value }}" @selected(request('period', 'all_time') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 d-flex justify-content-end">
                <button class="btn btn-dark rounded-pill px-4">Apply Filters</button>
            </div>
        </form>
    </div>

    <div class="row g-3 mb-4">
        @foreach ([
            'Total Tickets' => $stats['total'],
            'Open Tickets' => $stats['open'],
            'Resolved Tickets' => $stats['resolved'],
            'Closed Tickets' => $stats['closed'],
            'Overdue Tickets' => $stats['overdue'],
            'Tickets Logged This Month' => $stats['this_month'],
            'Facilities With Tickets' => $stats['facilities'],
            'Systems With Tickets' => $stats['systems'],
            'Avg Resolution Hours' => $stats['average_resolution_hours'],
        ] as $label => $value)
            <div class="col-md-4 col-xl">
                <div class="metric-card p-3 h-100">
                    <div class="text-muted small">{{ $label }}</div>
                    <div class="fs-3 fw-bold">{{ $value }}</div>
                </div>
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
@endsection
