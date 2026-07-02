@extends('layouts.app', ['title' => 'Manage Tickets'])

@section('content')
    <div class="content-card bg-white p-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
            <div>
                <p class="text-uppercase text-muted fw-semibold small mb-1">Ticket Queue</p>
                <h1 class="h3 mb-0">Manage support tickets</h1>
            </div>
            <a href="{{ route('dashboard') }}" class="btn btn-outline-dark rounded-pill px-4">Back to Dashboard</a>
        </div>

        <form method="GET" class="row g-3 mb-4">
            <div class="col-md-4">
                <input type="text" name="search" class="form-control" placeholder="Search tickets" value="{{ request('search') }}">
            </div>
            <div class="col-md-4">
                <select name="status" class="form-select">
                    <option value="">All statuses</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->id }}" @selected(request('status') == $status->id)>{{ $status->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <button class="btn btn-dark rounded-pill px-4">Filter</button>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Ticket</th>
                        <th>Audit Trail</th>
                        <th>Reporter</th>
                        <th>Status</th>
                        <th>Priority</th>
                        <th>Assigned Staff</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($tickets as $ticket)
                        <tr>
                            <td>
                                <a href="{{ route('admin.tickets.show', $ticket) }}" class="fw-semibold">{{ $ticket->ticket_number }}</a>
                                <div class="text-muted small">{{ $ticket->system->name }}</div>
                            </td>
                            <td>
                                <a href="{{ route('admin.tickets.audit-trail', $ticket) }}" class="btn btn-sm btn-outline-secondary rounded-pill">View Audit Trail</a>
                            </td>
                            <td>{{ $ticket->full_name }}</td>
                            <td>{{ $ticket->status->name }}</td>
                            <td>{{ $ticket->priorityLevel->name }}</td>
                            <td>{{ $ticket->assignedStaff?->name ?? 'Unassigned' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{ $tickets->links() }}
    </div>
@endsection
