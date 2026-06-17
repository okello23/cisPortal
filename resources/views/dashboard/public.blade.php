@extends('layouts.app', ['title' => 'Public Dashboard'])

@section('content')
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <p class="text-uppercase text-muted fw-semibold small mb-1">Public Transparency Dashboard</p>
            <h1 class="h2 mb-0">ICT support performance at a glance</h1>
        </div>
        <a class="btn btn-dark rounded-pill px-4" href="{{ route('tickets.create') }}">Log New Issue</a>
    </div>

    <div class="row g-3 mb-4">
        @foreach ([
            'Total Tickets' => $stats['total'],
            'Open Tickets' => $stats['open'],
            'Resolved Tickets' => $stats['resolved'],
            'Closed Tickets' => $stats['closed'],
            'Overdue Tickets' => $stats['overdue'],
            'Tickets Today' => $stats['today'],
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
                        @foreach ($bySystem as $row)
                            <tr><td>{{ $row->name ?? 'Unspecified' }}</td><td class="text-end">{{ $row->total }}</td></tr>
                        @endforeach
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="content-card bg-white p-4">
                <h2 class="h5">Tickets by Region</h2>
                <div class="table-responsive">
                    <table class="table table-sm">
                        @foreach ($byRegion as $row)
                            <tr><td>{{ $row->name ?? 'Unspecified' }}</td><td class="text-end">{{ $row->total }}</td></tr>
                        @endforeach
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="content-card bg-white p-4">
                <h2 class="h5">Top Facilities by Ticket Volume</h2>
                <div class="table-responsive">
                    <table class="table table-sm">
                        @foreach ($byFacility as $row)
                            <tr><td>{{ $row->name ?? 'Unspecified' }}</td><td class="text-end">{{ $row->total }}</td></tr>
                        @endforeach
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="content-card bg-white p-4">
                <h2 class="h5">Most Reported Issues</h2>
                <div class="table-responsive">
                    <table class="table table-sm">
                        @foreach ($commonIssues as $row)
                            <tr><td>{{ $row->name }}</td><td class="text-end">{{ $row->total }}</td></tr>
                        @endforeach
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
                                <tr><td colspan="3" class="text-center text-muted">No repeat issues identified yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
