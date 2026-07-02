@extends('layouts.app', ['title' => 'Audit Trail'])

@section('content')
    <div class="content-card bg-white p-4 p-lg-5">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
            <div>
                <p class="text-uppercase text-muted fw-semibold small mb-1">Ticket Timeline</p>
                <h1 class="h3 mb-1">{{ $ticket->ticket_number }} Audit Trail</h1>
                <p class="text-muted mb-0">{{ $ticket->system->name }} · {{ $ticket->status->name }}</p>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('admin.tickets.show', $ticket) }}" class="btn btn-outline-dark rounded-pill px-4">Back to Ticket</a>
                <a href="{{ route('admin.tickets.index') }}" class="btn btn-dark rounded-pill px-4">All Tickets</a>
            </div>
        </div>

        @if ($timeline->isEmpty())
            <div class="alert alert-light border rounded-4 mb-0">No audit trail events have been recorded for this ticket yet.</div>
        @else
            <div class="d-grid gap-3">
                @foreach ($timeline as $event)
                    <div class="border rounded-4 p-4">
                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
                            <div>
                                <h2 class="h6 mb-1">{{ $event['title'] }}</h2>
                                <div class="text-muted small">{{ $event['actor'] }}</div>
                            </div>
                            <span class="badge text-bg-{{ $event['tone'] }}">{{ $event['timestamp']->format('d M Y H:i') }}</span>
                        </div>
                        <div class="mb-0">{{ $event['details'] }}</div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@endsection
