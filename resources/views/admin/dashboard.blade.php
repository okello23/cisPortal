@extends('layouts.app', ['title' => 'ICT Dashboard'])

@section('content')
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <p class="text-uppercase text-muted fw-semibold small mb-1">Internal ICT Dashboard</p>
            <h1 class="h2 mb-0">Welcome, {{ auth()->user()->name }}</h1>
        </div>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="btn btn-outline-dark rounded-pill px-4">Logout</button>
        </form>
    </div>

    <div class="row g-3 mb-4">
        @foreach ([
            'Assigned Tickets' => $metrics['assigned'],
            'In Progress' => $metrics['in_progress'],
            'Resolved' => $metrics['resolved'],
            'SLA Compliant' => $metrics['sla_compliance'],
        ] as $label => $value)
            <div class="col-md-3">
                <div class="metric-card p-3">
                    <div class="text-muted small">{{ $label }}</div>
                    <div class="fs-3 fw-bold">{{ $value }}</div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="content-card bg-white p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h5 mb-0">Recent Tickets</h2>
                    <a href="{{ route('admin.tickets.index') }}" class="btn btn-sm btn-dark rounded-pill">View All</a>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr><th>Ticket</th><th>System</th><th>Status</th><th>Assigned</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($tickets as $ticket)
                                <tr>
                                    <td><a href="{{ route('admin.tickets.show', $ticket) }}">{{ $ticket->ticket_number }}</a></td>
                                    <td>{{ $ticket->system->name }}</td>
                                    <td>{{ $ticket->status->name }}</td>
                                    <td>{{ $ticket->assignedStaff?->name ?? 'Unassigned' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="content-card bg-white p-4">
                @if (auth()->user()->role === 'ict_admin')
                    <div class="mb-4">
                        <h2 class="h5">Administration</h2>
                        <a href="{{ \Illuminate\Support\Facades\Route::has('admin.users.index') ? route('admin.users.index') : url('/users') }}" class="btn btn-outline-dark rounded-4 w-100 text-start">Manage ICT Users</a>
                    </div>
                @endif
                <h2 class="h5">Manager Lists</h2>
                <div class="d-grid gap-2">
                    @foreach ([
                        'systems' => 'Systems',
                        'modules' => 'Modules',
                        'regions' => 'Regions',
                        'facilities' => 'Facilities',
                        'departments' => 'Departments',
                        'issue-types' => 'Issue Types',
                        'priority-levels' => 'Priority Levels',
                        'ticket-statuses' => 'Ticket Statuses',
                        'resolution-categories' => 'Resolution Categories',
                        'closure-reasons' => 'Closure Reasons',
                    ] as $key => $label)
                        <a href="{{ route('lists.index', $key) }}" class="btn btn-outline-secondary text-start rounded-4">{{ $label }}</a>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
@endsection
