@extends('layouts.app', ['title' => 'Track Ticket'])

@section('content')
    <div class="row justify-content-center g-4">
        <div class="col-lg-6">
            <div class="content-card bg-white p-4">
                <h1 class="h3 mb-3">Track your ticket</h1>
                <p class="text-muted">Use your ticket number and the same email address or phone number used during submission.</p>
                <form method="POST" action="{{ route('tickets.track.search') }}" class="row g-3">
                    @csrf
                    <div class="col-12">
                        <label class="form-label">Ticket Number</label>
                        <input type="text" name="ticket_number" class="form-control" value="{{ old('ticket_number', session('ticket_number')) }}" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Email Address or Phone Number</label>
                        <input type="text" name="contact" class="form-control" value="{{ old('contact') }}" required>
                    </div>
                    <div class="col-12">
                        <button class="btn btn-dark rounded-pill px-4">Track Ticket</button>
                    </div>
                </form>
            </div>
        </div>

        @isset($ticket)
            <div class="col-lg-8">
                <div class="content-card bg-white p-4">
                    @if ($ticket)
                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
                            <div>
                                <p class="text-uppercase text-muted small mb-1">Ticket Number</p>
                                <h2 class="h3 mb-0">{{ $ticket->ticket_number }}</h2>
                            </div>
                            <span class="badge text-bg-{{ $ticket->status?->color ?? 'secondary' }} fs-6">{{ $ticket->status?->name }}</span>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6"><strong>Assigned Staff:</strong> {{ $ticket->assignedStaff?->name ?? 'Pending assignment' }}</div>
                            <div class="col-md-6"><strong>Designation:</strong> {{ $ticket->designation?->name ?? 'N/A' }}</div>
                            <div class="col-md-6"><strong>Lab Manager:</strong> {{ $ticket->lab_manager_name ?? 'N/A' }}</div>
                            <div class="col-md-6"><strong>Lab Manager Email:</strong> {{ $ticket->lab_manager_email ?? 'N/A' }}</div>
                            <div class="col-md-6"><strong>District:</strong> {{ $ticket->district_name ?? 'N/A' }}</div>
                            <div class="col-md-6"><strong>Issue Began:</strong> {{ optional($ticket->issue_started_at)->format('d M Y') ?? 'N/A' }}</div>
                            <div class="col-md-6"><strong>Expected Resolution Date:</strong> {{ optional($ticket->expected_resolution_date)->format('d M Y') ?? 'Not yet set' }}</div>
                            <div class="col-12"><strong>Latest Update:</strong> {{ optional($ticket->updated_at)->format('d M Y H:i') }}</div>
                            <div class="col-12"><strong>Resolution Summary:</strong> {{ $ticket->resolution_summary ?? 'Resolution details will appear here once available.' }}</div>
                        </div>

                        @if ($ticket->canReceiveFeedback())
                            <div class="alert alert-info rounded-4 mt-4 mb-0">
                                This ticket has been resolved. <a href="{{ $ticket->feedbackUrl() }}" class="alert-link">Rate the support experience</a>.
                            </div>
                        @elseif ($ticket->feedback)
                            <div class="alert alert-success rounded-4 mt-4 mb-0">
                                Feedback received. Thank you for rating the support experience.
                            </div>
                        @endif
                    @else
                        <div class="alert alert-warning mb-0">No ticket matched the details provided.</div>
                    @endif
                </div>
            </div>
        @endisset
    </div>
@endsection
