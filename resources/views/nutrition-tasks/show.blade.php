@extends('layouts.app', ['title' => $task->task_number])

@section('content')
<div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4"><div><p class="text-uppercase text-muted fw-semibold small mb-1">Nutrition Task</p><h1 class="h2 mb-0">{{ $task->task_number }}</h1></div><a href="{{ route('nutrition-tasks.index') }}" class="btn btn-outline-dark rounded-pill px-4">Back to Tasks</a></div>
<div class="row g-4">
    <div class="col-lg-8">
        <div class="content-card bg-white p-4 mb-4">
            <div class="d-flex justify-content-between flex-wrap gap-2 mb-4"><div><div class="text-muted small">Request Type</div><div class="h5">{{ $task->requestTypeLabel() }}</div></div><div><span class="stats-badge stats-badge--orange">{{ \App\Models\NutritionTask::PRIORITIES[$task->priority] }} Priority</span></div></div>
            <h2 class="h5">Task Description</h2><p class="mb-4" style="white-space:pre-line">{{ $task->description }}</p>
            <div class="row g-3"><div class="col-md-6"><div class="text-muted small">Requestor</div><strong>{{ $task->requestor_name }}</strong><div>{{ $task->requestor_designation }}</div><div>{{ $task->requestor_place_of_work }}</div></div><div class="col-md-6"><div class="text-muted small">Contact</div><div>{{ $task->requestor_email }}</div><div>{{ $task->requestor_phone }}</div></div><div class="col-md-6"><div class="text-muted small">Preferred Assignee</div><div>{{ $task->requested_assignee_name ?: 'Not specified' }}</div><div>{{ $task->requested_assignee_email }}</div></div><div class="col-md-6"><div class="text-muted small">Entry Date</div><div>{{ $task->entry_date->format('d F Y') }}</div></div></div>
        </div>
        <div class="content-card bg-white p-4"><h2 class="h5 mb-3">Activity History</h2><div class="list-group list-group-flush">
        @forelse($task->histories as $history)<div class="list-group-item px-0"><div class="d-flex justify-content-between gap-3"><strong>{{ $history->changedBy?->name ?? 'Public requestor' }}</strong><span class="small text-muted">{{ $history->created_at->format('d M Y H:i') }}</span></div>@if($history->old_status !== $history->new_status && $history->new_status)<div>Status: {{ $statuses[$history->old_status] ?? 'New' }} → {{ $statuses[$history->new_status] ?? $history->new_status }}</div>@endif @if($history->old_assignee_id !== $history->new_assignee_id)<div>Assigned to: {{ $history->newAssignee?->user?->name ?? 'Unassigned' }}</div>@endif @if($history->note)<p class="mb-0 mt-1 text-muted">{{ $history->note }}</p>@endif</div>@empty<div class="text-muted">No activity recorded.</div>@endforelse
        </div></div>
    </div>
    <div class="col-lg-4">
        <div class="content-card bg-white p-4 position-sticky" style="top:1rem"><h2 class="h5 mb-3">Update Task</h2><form method="POST" action="{{ route('nutrition-tasks.update',$task) }}" class="row g-3">@csrf @method('PUT')
            @if($isTaskAdmin)<div class="col-12"><label class="form-label">Assign To</label><select name="assigned_to" class="form-select"><option value="">Unassigned</option>@foreach($teamMembers as $member)<option value="{{ $member->id }}" @selected($task->assigned_to === $member->id)>{{ $member->user->name }} — {{ $member->designation }}</option>@endforeach</select></div><div class="col-12"><label class="form-label">Due Date</label><input type="date" name="due_date" value="{{ $task->due_date?->toDateString() }}" class="form-control"></div>@endif
            <div class="col-12"><label class="form-label">Progress Status</label><select name="status" class="form-select" required>@foreach($statuses as $value=>$label)<option value="{{ $value }}" @selected($task->status===$value)>{{ $label }}</option>@endforeach</select></div>
            <div class="col-12"><label class="form-label">Progress Notes</label><textarea name="progress_notes" rows="4" class="form-control" placeholder="Add an update or completion note">{{ $task->progress_notes }}</textarea></div>
            <div class="col-12"><button class="btn btn-cis-orange rounded-pill px-4 w-100">Save Update</button></div>
        </form><hr><dl class="row small mb-0"><dt class="col-5">Current assignee</dt><dd class="col-7">{{ $task->assignee?->user?->name ?? 'Unassigned' }}</dd><dt class="col-5">Due date</dt><dd class="col-7">{{ $task->due_date?->format('d M Y') ?? 'Not set' }}</dd><dt class="col-5">Assigned by</dt><dd class="col-7">{{ $task->assigner?->name ?? 'N/A' }}</dd></dl></div>
    </div>
</div>
@endsection
