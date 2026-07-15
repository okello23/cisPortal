@extends('layouts.app', ['title' => 'Quarantined Submissions'])

@section('content')
    <div class="content-card bg-white p-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
            <div>
                <p class="text-uppercase text-muted fw-semibold small mb-1">Review Queue</p>
                <h1 class="h3 mb-0">Quarantined submissions</h1>
            </div>
            <a href="{{ route('admin.anti-spam.dashboard') }}" class="btn btn-outline-dark rounded-pill px-4">Back to Dashboard</a>
        </div>

        @foreach ($tickets as $ticket)
            <div class="border rounded-4 p-4 mb-4">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                    <div>
                        <h2 class="h5 mb-1">{{ $ticket->ticket_number }}</h2>
                        <div class="text-muted small">{{ $ticket->full_name }} | {{ $ticket->email }} | {{ $ticket->ip_address }}</div>
                    </div>
                    <span class="badge text-bg-warning">{{ $ticket->submission_risk_level }}</span>
                </div>
                <div class="row g-3 mt-1">
                    <div class="col-md-6"><strong>System:</strong> {{ $ticket->system?->name }}</div>
                    <div class="col-md-6"><strong>Facility:</strong> {{ $ticket->facility?->name }}</div>
                    <div class="col-md-6"><strong>Risk Score:</strong> {{ $ticket->submission_risk_score }}</div>
                    <div class="col-md-6"><strong>Duplicate:</strong> {{ $ticket->duplicateParent?->ticket_number ?? 'None' }}</div>
                    <div class="col-12"><strong>Reasons:</strong> {{ collect($ticket->submission_risk_reasons)->pluck('rule')->implode(', ') ?: 'None recorded' }}</div>
                    <div class="col-12"><strong>Description:</strong><br>{{ $ticket->description }}</div>
                    <div class="col-12">
                        <strong>Attachments:</strong>
                        @forelse ($ticket->attachments as $attachment)
                            <div><a href="{{ $attachment->downloadUrl() }}">{{ $attachment->original_filename }}</a> ({{ $attachment->status }})</div>
                        @empty
                            <div class="text-muted">No attachments.</div>
                        @endforelse
                    </div>
                </div>
                <form method="POST" action="{{ route('admin.anti-spam.quarantine.update', $ticket) }}" class="row g-2 mt-3">
                    @csrf
                    @method('PUT')
                    <div class="col-md-4">
                        <select name="action" class="form-select">
                            <option value="approve">Approve as Genuine</option>
                            <option value="release">Release to Ticket Queue</option>
                            <option value="reject_spam">Reject as Spam</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <input type="text" name="reason" class="form-control" placeholder="Internal review note">
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-dark w-100">Save</button>
                    </div>
                </form>
            </div>
        @endforeach

        {{ $tickets->links() }}
    </div>
@endsection
