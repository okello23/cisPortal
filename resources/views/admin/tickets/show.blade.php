@extends('layouts.app', ['title' => $ticket->ticket_number])

@section('content')
    @php
        $statusCode = strtolower(trim((string) $ticket->status?->code));
        $statusName = strtolower(trim((string) $ticket->status?->name));
        $isClosedTicket = $ticket->closed_at !== null || $statusCode === 'closed' || $statusName === 'closed';
        $isResolvedTicket = $ticket->resolved_at !== null || $statusCode === 'resolved' || $statusName === 'resolved';
        $isCompletedTicket = $isClosedTicket || $isResolvedTicket;
        $isAssignedTicket = $statusCode === 'assigned' || $statusName === 'assigned';
        $hasUpdateErrors = $errors->isNotEmpty();
        $hideUpdateCardByDefault = ! $isCompletedTicket && $isAssignedTicket && ! $hasUpdateErrors;
        $completionLog = $ticket->statusLogs
            ->filter(fn ($log) => in_array($log->newStatus?->code, ['resolved', 'closed'], true))
            ->sortByDesc('created_at')
            ->first();
        $turnaroundInterval = ($isClosedTicket ? $ticket->closed_at : $ticket->resolved_at)?->diff($ticket->created_at);
    @endphp

    <div class="row g-4">
        <div class="{{ $hideUpdateCardByDefault ? 'col-lg-9' : 'col-lg-7' }}" id="ticket-details-column">
            <div class="content-card bg-white p-4 mb-4">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
                    <div>
                        <p class="text-uppercase text-muted fw-semibold small mb-1">Ticket Details</p>
                        <h1 class="h3 mb-0">{{ $ticket->ticket_number }}</h1>
                    </div>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        @if ($isCompletedTicket)
                            <a href="{{ route('admin.tickets.incident-resolution-report', $ticket) }}" class="btn btn-sm btn-outline-dark rounded-pill">
                                Download Resolution Report
                            </a>
                        @endif
                        <span class="badge text-bg-{{ $ticket->status->color ?? 'secondary' }} fs-6">{{ $ticket->status->name }}</span>
                    </div>
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
                        <strong>Attachments:</strong><br>
                        @if ($ticket->attachments->isNotEmpty())
                            @foreach ($ticket->attachments as $attachment)
                                <div class="mb-3">
                                    @if ($attachment->isPreviewableImage())
                                        <div class="mb-2">
                                            <a href="{{ $attachment->previewUrl() }}" target="_blank" rel="noopener">
                                                <img
                                                    src="{{ $attachment->previewUrl() }}"
                                                    alt="{{ $attachment->original_filename }}"
                                                    class="img-fluid rounded-3 border"
                                                    style="max-height: 260px;"
                                                >
                                            </a>
                                        </div>
                                        <a href="{{ $attachment->downloadUrl() }}" rel="noopener">
                                            {{ $attachment->original_filename }}
                                        </a>
                                    @else
                                        <a href="{{ $attachment->downloadUrl() }}" rel="noopener">
                                            {{ $attachment->original_filename }}
                                        </a>
                                    @endif
                                </div>
                            @endforeach
                        @elseif ($ticket->hasAttachment())
                            <div>
                                <a href="{{ $ticket->attachmentUrl() }}" target="_blank" rel="noopener">
                                    {{ $ticket->attachmentFilename() }}
                                </a>
                            </div>
                        @else
                            No attachment uploaded.
                        @endif
                    </div>
                    <div class="col-12"><strong>Root Cause Analysis:</strong><br>{{ $ticket->root_cause_analysis ?? 'Not recorded yet.' }}</div>
                    <div class="col-12"><strong>Verification / Testing:</strong><br>{{ $ticket->verification_testing ?? 'Not recorded yet.' }}</div>
                    <div class="col-md-6"><strong>Data Loss Risk:</strong><br>{{ $ticket->data_loss_risk ? ucfirst($ticket->data_loss_risk) : 'Not recorded yet.' }}</div>
                    <div class="col-md-6"><strong>Services Disrupted:</strong><br>{{ $ticket->services_disrupted ?? 'Not recorded yet.' }}</div>
                    <div class="col-12"><strong>Resolution Summary:</strong><br>{{ $ticket->resolution_summary ?? 'No resolution summary yet.' }}</div>
                    <div class="col-12"><strong>Work Done:</strong><br>{{ $ticket->work_done ?? 'Not recorded yet.' }}</div>
                    <div class="col-12"><strong>Recommendations:</strong><br>{{ $ticket->recommendations ?? 'Not recorded yet.' }}</div>
                    <div class="col-12"><strong>Challenges Faced:</strong><br>{{ $ticket->challenges_faced ?? 'No challenges recorded.' }}</div>
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

        <div class="{{ $hideUpdateCardByDefault ? 'col-lg-3' : 'col-lg-5' }}" id="ticket-side-column">
            @if (! $isCompletedTicket && $isAssignedTicket)
                <div class="content-card bg-white p-4 mb-4">
                    <button
                        type="button"
                        class="btn btn-dark rounded-pill px-4"
                        id="show-update-ticket-card"
                        aria-controls="update-ticket-card"
                        aria-expanded="{{ $hasUpdateErrors ? 'true' : 'false' }}"
                    >
                        Update Assignment
                    </button>
                </div>
            @endif

            <div
                class="content-card bg-white p-4"
                id="update-ticket-card"
                @if ($hideUpdateCardByDefault) style="display: none;" @endif
            >
                @if ($isCompletedTicket)
                    @if ($isClosedTicket)
                        @php
                            $overallRating = (int) ($ticket->feedback?->overall_satisfaction_rating ?? 0);
                        @endphp
                        <h2 class="h5 mb-3">Customer Rating</h2>
                        <p class="text-muted small mb-3">This ticket is closed. The support experience rating shared by the requestor is shown below.</p>

                        @if ($ticket->feedback)
                            <div class="mb-3" aria-label="Overall rating {{ $overallRating }} out of 5">
                                @for ($star = 1; $star <= 5; $star++)
                                    <span style="font-size: 1.5rem; color: {{ $star <= $overallRating ? '#f4b400' : '#d1d5db' }};">&#9733;</span>
                                @endfor
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <strong>Timeliness:</strong><br>
                                    {{ $ticket->feedback->timeliness_rating }}/5
                                </div>
                                <div class="col-md-6">
                                    <strong>Completeness:</strong><br>
                                    {{ $ticket->feedback->completeness_rating }}/5
                                </div>
                                <div class="col-12">
                                    <strong>Overall Satisfaction:</strong><br>
                                    {{ $ticket->feedback->overall_satisfaction_rating }}/5
                                </div>
                                <div class="col-12">
                                    <strong>Comments:</strong><br>
                                    {{ $ticket->feedback->comments ?: 'No additional comments were provided.' }}
                                </div>
                            </div>
                        @else
                            <p class="text-muted mb-0">No customer rating has been submitted for this closed ticket yet.</p>
                        @endif
                    @else
                        <h2 class="h5 mb-3">Resolution Highlights</h2>
                        <p class="text-muted small mb-3">This ticket is already {{ $ticket->status->name }}. Key closure details are shown below.</p>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <strong>{{ $isClosedTicket ? 'Closed On' : 'Resolved On' }}:</strong><br>
                                {{ optional($isClosedTicket ? $ticket->closed_at : $ticket->resolved_at)->format('d M Y H:i') ?? 'N/A' }}
                            </div>
                            <div class="col-md-6">
                                <strong>{{ $isClosedTicket ? 'Closed By' : 'Resolved By' }}:</strong><br>
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
                            <div class="col-12">
                                <strong>Root Cause Analysis:</strong><br>
                                {{ $ticket->root_cause_analysis ?? 'Not recorded yet.' }}
                            </div>
                            <div class="col-12">
                                <strong>Verification / Testing:</strong><br>
                                {{ $ticket->verification_testing ?? 'Not recorded yet.' }}
                            </div>
                            <div class="col-md-6">
                                <strong>Data Loss Risk:</strong><br>
                                {{ $ticket->data_loss_risk ? ucfirst($ticket->data_loss_risk) : 'Not recorded yet.' }}
                            </div>
                            <div class="col-md-6">
                                <strong>Services Disrupted:</strong><br>
                                {{ $ticket->services_disrupted ?? 'Not recorded yet.' }}
                            </div>
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
                    @endif
                @else
                @php
                    $isNewTicket = $statusCode === 'new' || $statusName === 'new';
                @endphp
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
                    @if ($isNewTicket)
                        <input type="hidden" name="status_id" id="status_id" value="{{ $assignedStatusId }}" data-status-code="assigned">
                    @else
                        <div class="col-12">
                            <label class="form-label">Status</label>
                            <select name="status_id" class="form-select" id="status_id" required>
                                <option value="">Select Ticket status...</option>
                                @foreach ($statuses as $status)
                                    <option value="{{ $status->id }}" data-status-code="{{ $status->code }}" @selected($ticket->status_id == $status->id)>{{ $status->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div class="col-12 workflow-field workflow-field--assign" data-assign-mode="{{ $isWorkflowManager ? 'manager' : 'handler' }}">
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
                    <div class="col-md-12 workflow-field workflow-field--resolved">
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
                        <label class="form-label">Root Cause Analysis <span class="text-danger">*</span></label>
                        <textarea name="root_cause_analysis" rows="3" class="form-control">{{ old('root_cause_analysis', $ticket->root_cause_analysis) }}</textarea>
                    </div>
                    <div class="col-12 workflow-field workflow-field--resolved workflow-field--closed">
                        <label class="form-label">Verification / Testing <span class="text-danger">*</span></label>
                        <textarea name="verification_testing" rows="3" class="form-control" placeholder="Describe how the fix was confirmed to work (e.g. test result entry, sync confirmation, user sign-off).">{{ old('verification_testing', $ticket->verification_testing) }}</textarea>
                    </div>
                    <div class="col-12 workflow-field workflow-field--resolved workflow-field--closed">
                        <label class="form-label">Impact Assessment</label>
                    </div>
                    <div class="col-md-12 workflow-field workflow-field--resolved workflow-field--closed">
                        <label class="form-label">Data Loss Risk <span class="text-danger">*</span></label>
                        <select name="data_loss_risk" class="form-select">
                            <option value="">Select risk...</option>
                            <option value="none" @selected(old('data_loss_risk', $ticket->data_loss_risk) === 'none')>None</option>
                            <option value="partial" @selected(old('data_loss_risk', $ticket->data_loss_risk) === 'partial')>Partial</option>
                            <option value="full" @selected(old('data_loss_risk', $ticket->data_loss_risk) === 'full')>Full</option>
                        </select>
                    </div>
                    <div class="col-md-12 workflow-field workflow-field--resolved workflow-field--closed">
                        <label class="form-label">Services Disrupted <span class="text-danger">*</span></label>
                        <textarea name="services_disrupted" rows="3" class="form-control" placeholder="e.g. Sample reception, result entry, report printing, results synchronization">{{ old('services_disrupted', $ticket->services_disrupted) }}</textarea>
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
            const updateCard = document.getElementById('update-ticket-card');
            const revealButton = document.getElementById('show-update-ticket-card');
            const detailsColumn = document.getElementById('ticket-details-column');
            const sideColumn = document.getElementById('ticket-side-column');

            const applyExpandedLayout = (expanded) => {
                if (!detailsColumn || !sideColumn) {
                    return;
                }

                detailsColumn.classList.toggle('col-lg-7', !expanded);
                detailsColumn.classList.toggle('col-lg-9', expanded);
                sideColumn.classList.toggle('col-lg-5', !expanded);
                sideColumn.classList.toggle('col-lg-3', expanded);
            };

            if (revealButton && updateCard) {
                revealButton.addEventListener('click', () => {
                    updateCard.style.display = '';
                    revealButton.setAttribute('aria-expanded', 'true');
                    revealButton.closest('.content-card')?.style.setProperty('display', 'none');
                    applyExpandedLayout(false);
                });
            }

            if (!statusSelect) {
                return;
            }

            const toggleWorkflowFields = () => {
                const statusCode = statusSelect.tagName === 'SELECT'
                    ? statusSelect.options[statusSelect.selectedIndex]?.dataset.statusCode || ''
                    : statusSelect.dataset.statusCode || '';

                document.querySelectorAll('.workflow-field').forEach((field) => {
                    if (field.classList.contains('workflow-field--assign')) {
                        const assignMode = field.dataset.assignMode || '';

                        if (assignMode === 'handler') {
                            field.style.display = statusCode === 'escalated' ? '' : 'none';
                        } else {
                            field.style.display = statusCode === 'resolved' ? 'none' : '';
                        }

                        return;
                    }

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
