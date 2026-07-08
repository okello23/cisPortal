@extends('layouts.app', ['title' => $ticket->ticket_number])

@section('content')
    @php
        $isCompletedTicket = in_array($ticket->status?->code, ['resolved', 'closed'], true);
        $completionLog = $ticket->statusLogs
            ->filter(fn ($log) => in_array($log->newStatus?->code, ['resolved', 'closed'], true))
            ->sortByDesc('created_at')
            ->first();
        $turnaroundInterval = ($ticket->status?->code === 'closed' ? $ticket->closed_at : $ticket->resolved_at)?->diff($ticket->created_at);
    @endphp

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="content-card bg-white p-4 mb-4">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
                    <div>
                        <p class="text-uppercase text-muted fw-semibold small mb-1">Ticket Details</p>
                        <h1 class="h3 mb-0">{{ $ticket->ticket_number }}</h1>
                    </div>
                    <span class="badge text-bg-{{ $ticket->status->color ?? 'secondary' }} fs-6">{{ $ticket->status->name }}</span>
                </div>

                <div class="row g-3">
                    <div class="col-md-6"><strong>Reporter:</strong> {{ $ticket->full_name }}</div>
                    <div class="col-md-6"><strong>Designation:</strong> {{ $ticket->designation?->name ?? 'N/A' }}</div>
                    <div class="col-md-6"><strong>Contact:</strong> {{ $ticket->email ?? $ticket->phone ?? 'N/A' }}</div>
                    <div class="col-md-6"><strong>Lab Manager:</strong> {{ $ticket->lab_manager_name ?? 'N/A' }}</div>
                    <div class="col-md-6"><strong>Lab Manager Email:</strong> {{ $ticket->lab_manager_email ?? 'N/A' }}</div>
                    <div class="col-md-6"><strong>System:</strong> {{ $ticket->system->name }}</div>
                    <div class="col-md-6"><strong>District:</strong> {{ $ticket->district_name ?? 'N/A' }}</div>
                    <div class="col-md-6"><strong>Issue Began:</strong> {{ optional($ticket->issue_started_at)->format('d M Y') ?? 'N/A' }}</div>
                    <!-- <div class="col-md-6"><strong>Module:</strong> {{ $ticket->module?->name ?? 'N/A' }}</div> -->
                    <div class="col-md-6"><strong>Region:</strong> {{ $ticket->region?->name ?? 'N/A' }}</div>
                    <div class="col-md-6"><strong>Facility:</strong> {{ $ticket->facility?->name ?? 'N/A' }}</div>
                    <div class="col-md-6"><strong>Department:</strong> {{ $ticket->department?->name ?? 'N/A' }}</div>
                 <div class="col-md-6">
                    <strong>Impact of Issue:</strong>
                    @php
                    $badgeColors = [
                    'high'     => 'stats-badge stats-badge--red',
                    'critical' => 'stats-badge stats-badge--red',
                    'medium'   => 'stats-badge stats-badge--orange',
                    ];
                    $badgeClass = $badgeColors[strtolower($ticket->priorityLevel->name)] ?? 'stats-badge stats-badge--teal';
                    @endphp
                    <span class="{{ $badgeClass }}"> {{ ucfirst($ticket->priorityLevel->name) }} </span>
                </div>
  
                    <div class="col-12"><strong>Issue Description:</strong><br>{{ $ticket->description }}</div>
                    <div class="col-12">
                        <strong>Attachment:</strong><br>
                        @if ($ticket->hasAttachment())
                            <a href="{{ $ticket->attachmentUrl() }}" target="_blank" rel="noopener">
                                {{ $ticket->attachmentFilename() }}
                            </a>

                            @if ($ticket->hasImageAttachment())
                                <div class="mt-3">
                                    <img
                                        src="{{ $ticket->attachmentUrl() }}"
                                        alt="Ticket attachment preview"
                                        class="img-fluid rounded-4 border"
                                        style="max-height: 420px;"
                                    >
                                </div>
                            @elseif ($ticket->hasPdfAttachment())
                                <div class="mt-3">
                                    <iframe
                                        src="{{ $ticket->attachmentUrl() }}"
                                        title="Ticket attachment preview"
                                        class="w-100 rounded-4 border"
                                        style="height: 420px;"
                                    ></iframe>
                                </div>
                            @endif
                        @else
                            No attachment uploaded.
                        @endif
                    </div>
                    <div class="col-12"><strong>Resolution Summary:</strong><br>{{ $ticket->resolution_summary ?? 'No resolution summary yet.' }}</div>
                    <div class="col-12"><strong>Work Done:</strong><br>{{ $ticket->work_done ?? 'Not recorded yet.' }}</div>
                    <div class="col-12"><strong>Recommendations:</strong><br>{{ $ticket->recommendations ?? 'Not recorded yet.' }}</div>
                    <div class="col-12"><strong>Challenges Faced:</strong><br>{{ $ticket->challenges_faced ?? 'No challenges recorded.' }}</div>
                    <div class="col-12">
                        <strong>Customer Feedback:</strong><br>
                        @if ($ticket->feedback)
                            Timeliness {{ $ticket->feedback->timeliness_rating }}/5,
                            Completeness {{ $ticket->feedback->completeness_rating }}/5,
                            Overall {{ $ticket->feedback->overall_satisfaction_rating }}/5
                            @if ($ticket->feedback->comments)
                                <br>{{ $ticket->feedback->comments }}
                            @endif
                        @else
                            No customer rating submitted yet.
                        @endif
                    </div>
                </div>
            </div>

            <div class="content-card bg-white p-4">
                <h2 class="h5">Status History</h2>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Old Status</th>
                                <th>New Status</th>
                                <th>Changed By</th>
                                <th>Changed</th>
                                <th>TAT</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($statusHistory as $history)
                                <tr>
                                    <td>{{ $history['old_status'] }}</td>
                                    <td>{{ $history['new_status'] }}</td>
                                    <td>{{ $history['changed_by'] }}</td>
                                    <td>{{ $history['changed_at']->format('d M Y H:i') }}</td>
                                    <td>{{ $history['tat'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="content-card bg-white p-4 mb-4">
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

            <div class="content-card bg-white p-4">
                @if ($isCompletedTicket)
                    <h2 class="h5 mb-3">Resolution Highlights</h2>
                    <p class="text-muted small mb-3">This ticket is already {{ $ticket->status->name }}. Key closure details are shown below.</p>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <strong>{{ $ticket->status->code === 'closed' ? 'Closed On' : 'Resolved On' }}:</strong><br>
                            {{ optional($ticket->status->code === 'closed' ? $ticket->closed_at : $ticket->resolved_at)->format('d M Y H:i') ?? 'N/A' }}
                        </div>
                        <div class="col-md-6">
                            <strong>{{ $ticket->status->code === 'closed' ? 'Closed By' : 'Resolved By' }}:</strong><br>
                            {{ $completionLog?->changedBy?->name ?? $ticket->assignedStaff?->name ?? 'N/A' }}
                        </div>
                        <div class="col-md-6">
                            <strong>Handled By:</strong><br>
                            {{ $ticket->assignedStaff?->name ?? 'N/A' }}
                        </div>
                        <div class="col-md-6">
                            <strong>Turnaround Time:</strong><br>
                            {{ $turnaroundInterval ? \Carbon\CarbonInterval::instance($turnaroundInterval)->cascade()->forHumans(short: true) : 'N/A' }}
                        </div>
                        <div class="col-md-6">
                            <strong>Expected Resolution Date:</strong><br>
                            {{ optional($ticket->expected_resolution_date)->format('d M Y') ?? 'N/A' }}
                        </div>
                        <div class="col-md-6">
                            <strong>Resolution Category:</strong><br>
                            {{ $ticket->resolutionCategory?->name ?? 'N/A' }}
                        </div>
                        @if ($ticket->status->code === 'closed')
                            <div class="col-md-6">
                                <strong>Closure Reason:</strong><br>
                                {{ $ticket->closureReason?->name ?? 'N/A' }}
                            </div>
                        @endif
                        <div class="col-12">
                            <strong>Work Done:</strong><br>
                            {{ $ticket->work_done ?? 'Not recorded yet.' }}
                        </div>
                        <div class="col-12">
                            <strong>Recommendations:</strong><br>
                            {{ $ticket->recommendations ?? 'Not recorded yet.' }}
                        </div>
                        <div class="col-12">
                            <strong>Challenges Faced:</strong><br>
                            {{ $ticket->challenges_faced ?? 'No challenges recorded.' }}
                        </div>
                    </div>
                @else
                <h2 class="h5 mb-3">Update Ticket</h2>
                <p class="text-muted small mb-3">
                    @if ($isWorkflowManager)
                        Assign new tickets to ICT support staff, or reassign/escalate them when needed.
                    @else
                        As the assigned handler, you can resolve, close, or escalate this ticket to a developer or manager.
                    @endif
                </p>
                <form method="POST" action="{{ route('admin.tickets.update', $ticket) }}" class="row g-3">
                    @csrf
                    @method('PUT')
                    <div class="col-12">
                        <label class="form-label">Status</label>
                        <select name="status_id" class="form-select" id="status_id" required>
                            @foreach ($statuses as $status)
                                <option value="{{ $status->id }}" data-status-code="{{ $status->code }}" @selected($ticket->status_id == $status->id)>{{ $status->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">{{ $isWorkflowManager ? 'Assign To' : 'Escalate / Reassign To' }}</label>
                        <select name="assigned_to" class="form-select">
                            <option value="">Unassigned</option>
                            @foreach ($staff as $person)
                                <option value="{{ $person->id }}" @selected($ticket->assigned_to == $person->id)>{{ $person->name }} ({{ \App\Models\User::roleLabels()[$person->role] ?? $person->role }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Expected Resolution Date</label>
                        <input type="date" name="expected_resolution_date" class="form-control" value="{{ optional($ticket->expected_resolution_date)->toDateString() }}">
                    </div>
                    <div class="col-md-6 workflow-field workflow-field--resolved">
                        <label class="form-label">Resolution Category</label>
                        <select name="resolution_category_id" class="form-select">
                            <option value="">Select</option>
                            @foreach ($resolutionCategories as $category)
                                <option value="{{ $category->id }}" @selected($ticket->resolution_category_id == $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6 workflow-field workflow-field--closed">
                        <label class="form-label">Closure Reason</label>
                        <select name="closure_reason_id" class="form-select">
                            <option value="">Select</option>
                            @foreach ($closureReasons as $reason)
                                <option value="{{ $reason->id }}" @selected($ticket->closure_reason_id == $reason->id)>{{ $reason->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 workflow-field workflow-field--resolved workflow-field--closed">
                        <label class="form-label">Work Done <span class="text-danger">*</span></label>
                        <textarea name="work_done" rows="3" class="form-control">{{ old('work_done', $ticket->work_done) }}</textarea>
                    </div>
                    <div class="col-12 workflow-field workflow-field--resolved workflow-field--closed">
                        <label class="form-label">Recommendations <span class="text-danger">*</span></label>
                        <textarea name="recommendations" rows="3" class="form-control">{{ old('recommendations', $ticket->recommendations) }}</textarea>
                    </div>
                    <div class="col-12 workflow-field workflow-field--resolved workflow-field--closed">
                        <label class="form-label">Challenges Faced</label>
                        <textarea name="challenges_faced" rows="3" class="form-control">{{ old('challenges_faced', $ticket->challenges_faced) }}</textarea>
                    </div>
                    <div class="col-12">
                        <button class="btn btn-dark rounded-pill px-4">Save Update</button>
                    </div>
                </form>
                @endif
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const statusSelect = document.getElementById('status_id');

            if (!statusSelect) {
                return;
            }

            const toggleWorkflowFields = () => {
                const selectedOption = statusSelect.options[statusSelect.selectedIndex];
                const statusCode = selectedOption?.dataset.statusCode || '';

                document.querySelectorAll('.workflow-field').forEach((field) => {
                    const showResolved = statusCode === 'resolved' && field.classList.contains('workflow-field--resolved');
                    const showClosed = statusCode === 'closed' && field.classList.contains('workflow-field--closed');
                    const shouldShow = showResolved || showClosed;

                    field.style.display = shouldShow ? '' : 'none';
                });
            };

            statusSelect.addEventListener('change', toggleWorkflowFields);
            toggleWorkflowFields();
        });
    </script>
@endpush
