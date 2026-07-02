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

    <div class="d-flex gap-2 flex-wrap mb-4">
        <a href="{{ route('dashboard') }}" class="btn rounded-pill px-4 {{ $activeTab === 'overview' ? 'btn-dark' : 'btn-outline-dark' }}">Overview</a>
        <a href="{{ route('dashboard', ['tab' => 'performance']) }}" class="btn rounded-pill px-4 {{ $activeTab === 'performance' ? 'btn-dark' : 'btn-outline-dark' }}">Support Team Performance</a>
        @if ($canViewManagerDashboard)
            <a href="{{ route('dashboard', ['tab' => 'manager']) }}" class="btn rounded-pill px-4 {{ $activeTab === 'manager' ? 'btn-dark' : 'btn-outline-dark' }}">Manager Dashboard</a>
        @endif
    </div>

    @if ($activeTab === 'overview')
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
                            'designations' => 'Designations',
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
    @elseif ($activeTab === 'performance')
        <div class="content-card bg-white p-4 p-lg-5">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
                <div>
                    <p class="text-uppercase text-muted fw-semibold small mb-1">Performance Dashboard</p>
                    <h2 class="h3 mb-1">
                        {{ $performance['is_manager_scope'] ? 'Support Team Performance' : 'My Performance' }}
                    </h2>
                    <p class="text-muted mb-0">
                        {{ $performance['is_manager_scope'] ? 'All active ICT support staff are shown below.' : 'This view is scoped to your own assigned ticket performance.' }}
                    </p>
                    <p class="text-muted small mb-0 mt-2">First response time is measured from ticket assignment to the first staff action recorded by a comment or workflow status change. Current backlog counts assigned tickets that are still open and past their expected resolution date.</p>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="{{ route('dashboard.support-performance.export', ['format' => 'csv']) }}" class="btn btn-outline-dark rounded-pill px-4">Download CSV</a>
                    <a href="{{ route('dashboard.support-performance.export', ['format' => 'xls']) }}" class="btn btn-outline-dark rounded-pill px-4">Download XLS</a>
                    <a href="{{ route('dashboard.support-performance.export', ['format' => 'pdf']) }}" class="btn btn-dark rounded-pill px-4">Download PDF</a>
                </div>
            </div>

            <div class="row g-3 mb-4">
                @foreach ([
                    'Staff in Scope' => $performance['summary']['staff_count'],
                    'Assigned Tickets' => $performance['summary']['tickets_assigned'],
                    'Resolved Tickets' => $performance['summary']['tickets_resolved'],
                    'Closed Tickets' => $performance['summary']['tickets_closed'],
                    'Active Tickets' => $performance['summary']['active_tickets'],
                    'Current Backlog' => $performance['summary']['current_backlog'],
                    'Avg SLA Compliance' => $performance['summary']['avg_sla_compliance_rate'] === null ? 'N/A' : number_format($performance['summary']['avg_sla_compliance_rate'], 1).'%',
                    'Avg Customer Rating' => $performance['summary']['avg_customer_rating'] === null ? 'N/A' : number_format($performance['summary']['avg_customer_rating'], 1),
                ] as $label => $value)
                    <div class="col-md-3">
                        <div class="metric-card p-3">
                            <div class="text-muted small">{{ $label }}</div>
                            <div class="fs-4 fw-bold">{{ $value }}</div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="table-responsive mb-5">
                <table class="table table-bordered table-striped align-middle">
                    <thead>
                        <tr>
                            <th>Support Staff</th>
                            <th>Tickets Assigned</th>
                            <th>Tickets Resolved</th>
                            <th>Tickets Closed</th>
                            <th>Tickets Escalated</th>
                            <th>Avg First Response Time</th>
                            <th>Avg Resolution Time</th>
                            <th>SLA Compliance Rate</th>
                            <th>Avg Customer Rating</th>
                            <th>Reopened Tickets</th>
                            <th>Current Backlog</th>
                            <th>Active Tickets</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($performance['rows'] as $row)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $row['staff_name'] }}</div>
                                    <div class="text-muted small">{{ $row['role'] }}</div>
                                </td>
                                <td>{{ $row['tickets_assigned'] }}</td>
                                <td>{{ $row['tickets_resolved'] }}</td>
                                <td>{{ $row['tickets_closed'] }}</td>
                                <td>{{ $row['tickets_escalated'] }}</td>
                                <td>{{ $row['avg_first_response_hours'] === null ? 'N/A' : number_format($row['avg_first_response_hours'], 1).' hrs' }}</td>
                                <td>{{ $row['avg_resolution_hours'] === null ? 'N/A' : number_format($row['avg_resolution_hours'], 1).' hrs' }}</td>
                                <td>{{ $row['sla_compliance_rate'] === null ? 'N/A' : number_format($row['sla_compliance_rate'], 1).'%' }}</td>
                                <td>{{ $row['avg_customer_rating'] === null ? 'N/A' : number_format($row['avg_customer_rating'], 1).'/5' }}</td>
                                <td>{{ $row['reopened_tickets'] }}</td>
                                <td>{{ $row['current_backlog'] }}</td>
                                <td>{{ $row['active_tickets'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="12" class="text-center text-muted py-4">No support performance data is available yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="d-grid gap-4">
                @foreach ($performance['rows'] as $row)
                    <fieldset class="app-fieldset">
                        <legend>{{ $row['staff_name'] }} Monthly Performance Trends</legend>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Month</th>
                                        <th>Assigned</th>
                                        <th>Resolved</th>
                                        <th>Closed</th>
                                        <th>Avg Rating</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($row['monthly_trends'] as $trend)
                                        <tr>
                                            <td>{{ $trend['label'] }}</td>
                                            <td>{{ $trend['assigned'] }}</td>
                                            <td>{{ $trend['resolved'] }}</td>
                                            <td>{{ $trend['closed'] }}</td>
                                            <td>{{ $trend['rating'] === null ? 'N/A' : number_format($trend['rating'], 1).'/5' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </fieldset>
                @endforeach
            </div>
        </div>
    @else
        <div class="content-card bg-white p-4 p-lg-5">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
                <div>
                    <p class="text-uppercase text-muted fw-semibold small mb-1">Manager Dashboard</p>
                    <h2 class="h3 mb-1">Operational Ticket Intelligence</h2>
                    <p class="text-muted mb-0">Real-time operational metrics for managers and administrators, with filterable workload, SLA, and customer satisfaction insights.</p>
                </div>
            </div>

            <form method="GET" action="{{ route('dashboard') }}" class="row g-3 mb-4">
                <input type="hidden" name="tab" value="manager">
                <div class="col-md-3">
                    <label class="form-label">Start Date</label>
                    <input type="date" name="manager_start_date" class="form-control" value="{{ $managerDashboard['filters']['start_date'] }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">End Date</label>
                    <input type="date" name="manager_end_date" class="form-control" value="{{ $managerDashboard['filters']['end_date'] }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Region</label>
                    <select name="manager_region_id" class="form-select">
                        <option value="">All regions</option>
                        @foreach ($managerDashboard['filter_options']['regions'] as $region)
                            <option value="{{ $region->id }}" @selected($managerDashboard['filters']['region_id'] == $region->id)>{{ $region->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Facility</label>
                    <select name="manager_facility_id" class="form-select">
                        <option value="">All facilities</option>
                        @foreach ($managerDashboard['filter_options']['facilities'] as $facility)
                            <option value="{{ $facility->id }}" @selected($managerDashboard['filters']['facility_id'] == $facility->id)>{{ $facility->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">System</label>
                    <select name="manager_system_id" class="form-select">
                        <option value="">All systems</option>
                        @foreach ($managerDashboard['filter_options']['systems'] as $system)
                            <option value="{{ $system->id }}" @selected($managerDashboard['filters']['system_id'] == $system->id)>{{ $system->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Support Staff</label>
                    <select name="manager_support_staff_id" class="form-select">
                        <option value="">All support staff</option>
                        @foreach ($managerDashboard['filter_options']['support_staff'] as $supportStaff)
                            <option value="{{ $supportStaff->id }}" @selected($managerDashboard['filters']['support_staff_id'] == $supportStaff->id)>{{ $supportStaff->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Ticket Category</label>
                    <select name="manager_issue_type_id" class="form-select">
                        <option value="">All categories</option>
                        @foreach ($managerDashboard['filter_options']['issue_types'] as $issueType)
                            <option value="{{ $issueType->id }}" @selected($managerDashboard['filters']['issue_type_id'] == $issueType->id)>{{ $issueType->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end gap-2">
                    <button class="btn btn-dark rounded-pill px-4">Apply Filters</button>
                    <a href="{{ route('dashboard', ['tab' => 'manager']) }}" class="btn btn-outline-dark rounded-pill px-4">Reset</a>
                </div>
            </form>

            <div class="row g-3 mb-4">
                @foreach ([
                    'Total Open Tickets' => $managerDashboard['summary']['total_open_tickets'],
                    'Average Response Time' => $managerDashboard['summary']['average_response_time_hours'] === null ? 'N/A' : number_format($managerDashboard['summary']['average_response_time_hours'], 1).' hrs',
                    'Average Resolution Time' => $managerDashboard['summary']['average_resolution_time_hours'] === null ? 'N/A' : number_format($managerDashboard['summary']['average_resolution_time_hours'], 1).' hrs',
                    'SLA Compliance' => $managerDashboard['summary']['sla_compliance_rate'] === null ? 'N/A' : number_format($managerDashboard['summary']['sla_compliance_rate'], 1).'%',
                    'Overdue Tickets' => $managerDashboard['summary']['overdue_tickets'],
                    'Escalated Tickets' => $managerDashboard['summary']['escalated_tickets'],
                ] as $label => $value)
                    <div class="col-md-4 col-xl-2">
                        <div class="metric-card p-3 h-100">
                            <div class="text-muted small">{{ $label }}</div>
                            <div class="fs-4 fw-bold">{{ $value }}</div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="row g-4">
                <div class="col-lg-6">
                    <fieldset class="app-fieldset h-100">
                        <legend>Tickets by Status</legend>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped mb-0">
                                <thead><tr><th>Status</th><th>Total</th></tr></thead>
                                <tbody>
                                    @foreach ($managerDashboard['tickets_by_status'] as $row)
                                        <tr><td>{{ $row['label'] }}</td><td>{{ $row['value'] }}</td></tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </fieldset>
                </div>
                <div class="col-lg-6">
                    <fieldset class="app-fieldset h-100">
                        <legend>Tickets by Impact on System Use</legend>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped mb-0">
                                <thead><tr><th>Impact Level</th><th>Total</th></tr></thead>
                                <tbody>
                                    @foreach ($managerDashboard['tickets_by_priority'] as $row)
                                        <tr><td>{{ $row['label'] }}</td><td>{{ $row['value'] }}</td></tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </fieldset>
                </div>
                <div class="col-lg-4">
                    <fieldset class="app-fieldset h-100">
                        <legend>Tickets by System</legend>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped mb-0">
                                <thead><tr><th>System</th><th>Total</th></tr></thead>
                                <tbody>
                                    @foreach ($managerDashboard['tickets_by_system'] as $row)
                                        <tr><td>{{ $row['label'] }}</td><td>{{ $row['value'] }}</td></tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </fieldset>
                </div>
                <div class="col-lg-4">
                    <fieldset class="app-fieldset h-100">
                        <legend>Tickets by Facility</legend>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped mb-0">
                                <thead><tr><th>Facility</th><th>Total</th></tr></thead>
                                <tbody>
                                    @foreach ($managerDashboard['tickets_by_facility'] as $row)
                                        <tr><td>{{ $row['label'] }}</td><td>{{ $row['value'] }}</td></tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </fieldset>
                </div>
                <div class="col-lg-4">
                    <fieldset class="app-fieldset h-100">
                        <legend>Tickets by Region</legend>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped mb-0">
                                <thead><tr><th>Region</th><th>Total</th></tr></thead>
                                <tbody>
                                    @foreach ($managerDashboard['tickets_by_region'] as $row)
                                        <tr><td>{{ $row['label'] }}</td><td>{{ $row['value'] }}</td></tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </fieldset>
                </div>
                <div class="col-lg-6">
                    <fieldset class="app-fieldset h-100">
                        <legend>Staff Workload Distribution</legend>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped mb-0">
                                <thead><tr><th>Support Staff</th><th>Active</th><th>Resolved</th><th>Closed</th><th>Total</th></tr></thead>
                                <tbody>
                                    @foreach ($managerDashboard['staff_workload'] as $row)
                                        <tr>
                                            <td>{{ $row['label'] }}</td>
                                            <td>{{ $row['active'] }}</td>
                                            <td>{{ $row['resolved'] }}</td>
                                            <td>{{ $row['closed'] }}</td>
                                            <td>{{ $row['total'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </fieldset>
                </div>
                <div class="col-lg-6">
                    <fieldset class="app-fieldset h-100">
                        <legend>Top Performing Support Staff</legend>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped mb-0">
                                <thead><tr><th>Support Staff</th><th>Resolved</th><th>SLA</th><th>Rating</th><th>Score</th></tr></thead>
                                <tbody>
                                    @foreach ($managerDashboard['top_performing_staff'] as $row)
                                        <tr>
                                            <td>{{ $row['label'] }}</td>
                                            <td>{{ $row['resolved'] }}</td>
                                            <td>{{ number_format($row['sla_compliance_rate'], 1) }}%</td>
                                            <td>{{ number_format($row['average_rating'], 1) }}/5</td>
                                            <td>{{ number_format($row['score'], 1) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </fieldset>
                </div>
                <div class="col-lg-6">
                    <fieldset class="app-fieldset h-100">
                        <legend>Lowest Rated Support Staff</legend>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped mb-0">
                                <thead><tr><th>Support Staff</th><th>Average Rating</th><th>Rated Tickets</th></tr></thead>
                                <tbody>
                                    @forelse ($managerDashboard['lowest_rated_staff'] as $row)
                                        <tr>
                                            <td>{{ $row['label'] }}</td>
                                            <td>{{ number_format($row['average_rating'], 1) }}/5</td>
                                            <td>{{ $row['rated_tickets'] }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="3" class="text-center text-muted">No customer ratings yet.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </fieldset>
                </div>
                <div class="col-lg-6">
                    <fieldset class="app-fieldset h-100">
                        <legend>Resolution Code Statistics</legend>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped mb-0">
                                <thead><tr><th>Resolution Code</th><th>Total</th></tr></thead>
                                <tbody>
                                    @forelse ($managerDashboard['resolution_code_statistics'] as $row)
                                        <tr><td>{{ $row['label'] }}</td><td>{{ $row['value'] }}</td></tr>
                                    @empty
                                        <tr><td colspan="2" class="text-center text-muted">No resolution codes recorded yet.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </fieldset>
                </div>
                <div class="col-lg-6">
                    <fieldset class="app-fieldset h-100">
                        <legend>Customer Satisfaction Trends</legend>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped mb-0">
                                <thead><tr><th>Month</th><th>Average Rating</th><th>Responses</th></tr></thead>
                                <tbody>
                                    @foreach ($managerDashboard['customer_satisfaction_trends'] as $row)
                                        <tr>
                                            <td>{{ $row['label'] }}</td>
                                            <td>{{ $row['average_rating'] === null ? 'N/A' : number_format($row['average_rating'], 1).'/5' }}</td>
                                            <td>{{ $row['responses'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </fieldset>
                </div>
                <div class="col-lg-6">
                    <fieldset class="app-fieldset h-100">
                        <legend>Monthly Ticket Trends</legend>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped mb-0">
                                <thead><tr><th>Month</th><th>Total</th><th>Resolved</th><th>Closed</th></tr></thead>
                                <tbody>
                                    @foreach ($managerDashboard['monthly_ticket_trends'] as $row)
                                        <tr>
                                            <td>{{ $row['label'] }}</td>
                                            <td>{{ $row['total'] }}</td>
                                            <td>{{ $row['resolved'] }}</td>
                                            <td>{{ $row['closed'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </fieldset>
                </div>
                <div class="col-lg-6">
                    <fieldset class="app-fieldset h-100">
                        <legend>Annual Ticket Trends</legend>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped mb-0">
                                <thead><tr><th>Year</th><th>Total</th><th>Resolved</th><th>Closed</th></tr></thead>
                                <tbody>
                                    @foreach ($managerDashboard['annual_ticket_trends'] as $row)
                                        <tr>
                                            <td>{{ $row['label'] }}</td>
                                            <td>{{ $row['total'] }}</td>
                                            <td>{{ $row['resolved'] }}</td>
                                            <td>{{ $row['closed'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </fieldset>
                </div>
            </div>
        </div>
    @endif
@endsection
