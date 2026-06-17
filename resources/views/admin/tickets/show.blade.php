@extends('layouts.app', ['title' => $ticket->ticket_number])

@section('content')
    <div class="row g-4">
        <div class="col-lg-7">
            <div class="content-card bg-white p-4 mb-4">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
                    <div>
                        <p class="text-uppercase text-muted fw-semibold small mb-1">Ticket Details</p>
                        <h1 class="h3 mb-0">{{ $ticket->ticket_number }}</h1>
                    </div>
                    <span class="badge text-bg-info fs-6">{{ $ticket->status->name }}</span>
                </div>

                <div class="row g-3">
                    <div class="col-md-6"><strong>Reporter:</strong> {{ $ticket->full_name }}</div>
                    <div class="col-md-6"><strong>Contact:</strong> {{ $ticket->email ?? $ticket->phone ?? 'N/A' }}</div>
                    <div class="col-md-6"><strong>System:</strong> {{ $ticket->system->name }}</div>
                    <div class="col-md-6"><strong>Module:</strong> {{ $ticket->module?->name ?? 'N/A' }}</div>
                    <div class="col-md-6"><strong>Region:</strong> {{ $ticket->region?->name ?? 'N/A' }}</div>
                    <div class="col-md-6"><strong>Facility:</strong> {{ $ticket->facility?->name ?? 'N/A' }}</div>
                    <div class="col-md-6"><strong>Department:</strong> {{ $ticket->department?->name ?? 'N/A' }}</div>
                    <div class="col-md-6"><strong>Priority:</strong> {{ $ticket->priorityLevel->name }}</div>
                    <div class="col-12"><strong>Description:</strong><br>{{ $ticket->description }}</div>
                    <div class="col-12"><strong>Resolution Summary:</strong><br>{{ $ticket->resolution_summary ?? 'No resolution summary yet.' }}</div>
                </div>
            </div>

            <div class="content-card bg-white p-4">
                <h2 class="h5">Status History</h2>
                <div class="table-responsive">
                    <table class="table">
                        <thead><tr><th>Old Status</th><th>New Status</th><th>Changed</th></tr></thead>
                        <tbody>
                            @foreach ($ticket->statusLogs as $log)
                                <tr>
                                    <td>{{ $log->oldStatus?->name ?? 'New Ticket' }}</td>
                                    <td>{{ $log->newStatus?->name }}</td>
                                    <td>{{ $log->created_at->format('d M Y H:i') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="content-card bg-white p-4 mb-4">
                <h2 class="h5 mb-3">Update Ticket</h2>
                <form method="POST" action="{{ route('admin.tickets.update', $ticket) }}" class="row g-3">
                    @csrf
                    @method('PUT')
                    <div class="col-12">
                        <label class="form-label">Status</label>
                        <select name="status_id" class="form-select" required>
                            @foreach ($statuses as $status)
                                <option value="{{ $status->id }}" @selected($ticket->status_id == $status->id)>{{ $status->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Assigned Staff</label>
                        <select name="assigned_to" class="form-select">
                            <option value="">Unassigned</option>
                            @foreach ($staff as $person)
                                <option value="{{ $person->id }}" @selected($ticket->assigned_to == $person->id)>{{ $person->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Resolution Category</label>
                        <select name="resolution_category_id" class="form-select">
                            <option value="">Select</option>
                            @foreach ($resolutionCategories as $category)
                                <option value="{{ $category->id }}" @selected($ticket->resolution_category_id == $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Closure Reason</label>
                        <select name="closure_reason_id" class="form-select">
                            <option value="">Select</option>
                            @foreach ($closureReasons as $reason)
                                <option value="{{ $reason->id }}" @selected($ticket->closure_reason_id == $reason->id)>{{ $reason->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Expected Resolution Date</label>
                        <input type="date" name="expected_resolution_date" class="form-control" value="{{ optional($ticket->expected_resolution_date)->toDateString() }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Resolution Summary</label>
                        <textarea name="resolution_summary" rows="3" class="form-control">{{ $ticket->resolution_summary }}</textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Comment / Note</label>
                        <textarea name="comment" rows="3" class="form-control"></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Comment Visibility</label>
                        <select name="comment_type" class="form-select">
                            <option value="internal">Internal</option>
                            <option value="public">Public</option>
                        </select>
                    </div>
                    <div class="col-md-6 d-flex align-items-end">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="training_recommended" id="training_recommended" value="1" @checked($ticket->training_recommended)>
                            <label class="form-check-label" for="training_recommended">Training Recommended</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <button class="btn btn-dark rounded-pill px-4">Save Update</button>
                    </div>
                </form>
            </div>

            <div class="content-card bg-white p-4">
                <h2 class="h5">Comments</h2>
                @forelse ($ticket->comments as $comment)
                    <div class="border rounded-4 p-3 mb-3">
                        <div class="d-flex justify-content-between small text-muted mb-2">
                            <span>{{ $comment->author?->name ?? 'System' }}</span>
                            <span>{{ ucfirst($comment->comment_type) }} note</span>
                        </div>
                        <div>{{ $comment->comment }}</div>
                    </div>
                @empty
                    <p class="text-muted mb-0">No comments added yet.</p>
                @endforelse
            </div>
        </div>
    </div>
@endsection
