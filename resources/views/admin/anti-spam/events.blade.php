@extends('layouts.app', ['title' => 'Security Events'])

@section('content')
    <div class="content-card bg-white p-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
            <div>
                <p class="text-uppercase text-muted fw-semibold small mb-1">Security Log</p>
                <h1 class="h3 mb-0">Public submission security events</h1>
            </div>
            <a href="{{ route('admin.anti-spam.dashboard') }}" class="btn btn-outline-dark rounded-pill px-4">Back to Dashboard</a>
        </div>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Event</th>
                        <th>IP</th>
                        <th>Action</th>
                        <th>Risk</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($events as $event)
                        <tr>
                            <td>{{ $event->created_at->format('d M Y H:i') }}</td>
                            <td>{{ $event->event_type }}</td>
                            <td>{{ $event->source_ip }}</td>
                            <td>{{ $event->action_taken ?? 'N/A' }}</td>
                            <td>{{ $event->risk_score ?? 'N/A' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{ $events->links() }}
    </div>
@endsection
