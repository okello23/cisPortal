@extends('layouts.app', ['title' => $task->task_number])

@section('content')
<div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4"><div><p class="text-uppercase text-muted fw-semibold small mb-1">Nutrition Task</p><h1 class="h2 mb-0">{{ $task->task_number }}</h1></div><a href="{{ route('nutrition-tasks.index') }}" class="btn btn-outline-dark rounded-pill px-4">Back to Tasks</a></div>
<div class="row g-4">
 <div class="col-lg-8">
  <div class="content-card bg-white p-4 mb-4">
   <div class="d-flex justify-content-between flex-wrap gap-2 mb-4"><div><div class="text-muted small">Request Type</div><div class="h5">{{ $task->requestTypeLabel() }}</div></div><span class="stats-badge stats-badge--orange">{{ \App\Models\NutritionTask::PRIORITIES[$task->priority] }} Priority</span></div>
   <h2 class="h5">Task Description</h2><p class="mb-4" style="white-space:pre-line">{{ $task->description }}</p>
   <p><strong>Theme:</strong> {{ $task->theme ? $task->theme->code.' — '.$task->theme->name : 'Not assigned to a theme' }}</p>
   <div class="row g-3"><div class="col-md-6"><div class="text-muted small">Requestor</div><strong>{{ $task->requestor_name }}</strong><div>{{ $task->requestor_designation }}</div><div>{{ $task->requestor_place_of_work }}</div></div><div class="col-md-6"><div class="text-muted small">Contact</div><div>{{ $task->requestor_email }}</div><div>{{ $task->requestor_phone }}</div></div></div>
  </div>

  <div class="content-card bg-white p-4 mb-4">
   <div class="d-flex justify-content-between align-items-center mb-3"><div><h2 class="h5 mb-1">Subtasks</h2><div class="small text-muted">A subtask without named assignees automatically belongs to the main task assignees.</div></div></div>
   @forelse($task->subtasks as $subtask)
    @php($effective = $subtask->assignees->isNotEmpty() ? $subtask->assignees : $task->assignees)
    @php($canUpdateSubtask = $isTaskAdmin || $effective->contains('id', auth()->user()->nutritionProfile?->id))
    <form method="POST" action="{{ route('nutrition-subtasks.update', [$task, $subtask]) }}" class="border rounded-4 p-3 mb-3">@csrf @method('PUT')
     <div class="row g-3 align-items-end">
      <div class="col-md-6"><label class="form-label">Subtask</label>@if($isTaskAdmin)<input name="title" value="{{ $subtask->title }}" class="form-control" required>@else<strong class="d-block">{{ $subtask->title }}</strong>@endif</div>
      @if($isTaskAdmin)<div class="col-md-3"><label class="form-label">Due date</label><input type="date" name="due_date" value="{{ $subtask->due_date?->toDateString() }}" class="form-control"></div>@endif
      <div class="col-md-3"><label class="form-label">Status</label><select name="status" class="form-select" @disabled(!$canUpdateSubtask)>@foreach($statuses as $value=>$label)<option value="{{ $value }}" @selected($subtask->status===$value)>{{ $label }}</option>@endforeach</select></div>
      <div class="col-md-6"><label class="form-label">Description</label>@if($isTaskAdmin)<textarea name="description" class="form-control" rows="3">{{ $subtask->description }}</textarea>@else<div>{{ $subtask->description ?: 'No description' }}</div>@endif</div>
      <div class="col-md-6">@if($isTaskAdmin)@include('nutrition-tasks.theme-select', ['fieldId' => 'subtask-'.$subtask->id, 'selectedTheme' => $subtask->theme_id])@else<label class="form-label">Theme</label><div>{{ $subtask->theme ? $subtask->theme->code.' — '.$subtask->theme->name : 'Not assigned to a theme' }}</div>@endif</div>
      @if($isTaskAdmin)<div class="col-12"><label class="form-label">Assign to <span class="text-muted fw-normal">(optional; leave blank to inherit main assignees)</span></label><select name="assignee_ids[]" class="form-select" multiple size="3">@foreach($teamMembers as $member)<option value="{{ $member->id }}" @selected($subtask->assignees->contains('id',$member->id))>{{ $member->user->name }} — {{ $member->designation }}</option>@endforeach</select></div>@endif
      <div class="col-12 d-flex justify-content-between align-items-center"><small class="text-muted">Assigned: {{ $effective->pluck('user.name')->join(', ') ?: 'No one' }}{{ $subtask->assignees->isEmpty() ? ' (inherited)' : '' }}</small>@if($canUpdateSubtask)<button class="btn btn-sm btn-outline-dark rounded-pill px-3">Save Subtask</button>@endif</div>
     </div>
    </form>
   @empty <div class="text-muted">No subtasks have been created.</div> @endforelse
   @if($isTaskAdmin)<hr><h3 class="h6">Add Subtask</h3><form method="POST" action="{{ route('nutrition-subtasks.store',$task) }}" class="row g-3">@csrf
    <div class="col-md-7"><label class="form-label">Title</label><input name="title" class="form-control" required></div>
    <div class="col-md-5"><label class="form-label">Due date</label><input type="date" name="due_date" class="form-control"></div>
    <div class="col-md-6"><label class="form-label">Description (optional)</label><textarea name="description" rows="3" class="form-control"></textarea></div>
    <div class="col-md-6">@include('nutrition-tasks.theme-select', ['fieldId' => 'new-subtask', 'selectedTheme' => null])</div>
    <div class="col-12"><label class="form-label">Assign to <span class="text-muted fw-normal">(optional)</span></label><select name="assignee_ids[]" class="form-select" multiple size="4">@foreach($teamMembers as $member)<option value="{{ $member->id }}">{{ $member->user->name }} — {{ $member->designation }}</option>@endforeach</select><div class="form-text">Leave blank to assign this subtask to everyone assigned to the main task.</div></div>
    <div class="col-12"><button class="btn btn-cis-orange rounded-pill px-4">Create Subtask</button></div>
   </form>@endif
  </div>

  <div class="content-card bg-white p-4"><h2 class="h5 mb-3">Activity History</h2><div class="list-group list-group-flush">
   @forelse($task->histories as $history)<div class="list-group-item px-0"><div class="d-flex justify-content-between gap-3"><strong>{{ $history->changedBy?->name ?? 'Public requestor' }}</strong><span class="small text-muted">{{ $history->created_at->format('d M Y H:i') }}</span></div>@if($history->old_status !== $history->new_status && $history->new_status)<div>Status: {{ $statuses[$history->old_status] ?? 'New' }} → {{ $statuses[$history->new_status] ?? $history->new_status }}</div>@endif @if($history->note)<p class="mb-0 mt-1 text-muted">{{ $history->note }}</p>@endif</div>@empty<div class="text-muted">No activity recorded.</div>@endforelse
  </div></div>
 </div>

 <div class="col-lg-4"><div class="content-card bg-white p-4 position-sticky" style="top:1rem"><h2 class="h5 mb-3">Update Main Task</h2>
  @if($canUpdateMainTask)<form method="POST" action="{{ route('nutrition-tasks.update',$task) }}" class="row g-3">@csrf @method('PUT')
   @if($isTaskAdmin)<div class="col-12"><label class="form-label d-block">Main Task Assignees <span class="text-muted fw-normal">(choose one or more)</span></label><div class="border rounded-3 p-3" style="max-height: 16rem; overflow-y: auto">@forelse($teamMembers as $member)<div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="assignee_ids[]" value="{{ $member->id }}" id="main-assignee-{{ $member->id }}" @checked(in_array($member->id, old('assignee_ids', $task->assignees->pluck('id')->all())))><label class="form-check-label w-100" for="main-assignee-{{ $member->id }}"><strong>{{ $member->user->name }}</strong>@if($member->designation)<span class="text-muted"> — {{ $member->designation }}</span>@endif</label></div>@empty<div class="text-muted small">No active Nutrition team members are available.</div>@endforelse</div><div class="form-text">Tick every person who should be assigned to this main task.</div></div><div class="col-12">@include('nutrition-tasks.theme-select', ['fieldId' => 'main', 'selectedTheme' => $task->theme_id])</div><div class="col-12"><label class="form-label">Due Date</label><input type="date" name="due_date" value="{{ old('due_date', $task->due_date?->toDateString()) }}" class="form-control"></div>@endif
   <div class="col-12"><label class="form-label">Progress Status</label><select name="status" class="form-select" required>@foreach($statuses as $value=>$label)<option value="{{ $value }}" @selected($task->status===$value)>{{ $label }}</option>@endforeach</select><div class="form-text">The main task can only be completed after every subtask is complete.</div></div>
   <div class="col-12"><label class="form-label">Progress Notes</label><textarea name="progress_notes" rows="4" class="form-control">{{ $task->progress_notes }}</textarea></div><div class="col-12"><button class="btn btn-cis-orange rounded-pill px-4 w-100">Save Update</button></div>
  </form>@else<div class="alert alert-info">You can view this main task because one of its subtasks is assigned to you. Update your assigned subtask above.</div>@endif
  <hr><dl class="row small mb-0"><dt class="col-5">Assignees</dt><dd class="col-7">@forelse($task->assignees as $person)<span class="badge text-bg-light border text-dark me-1 mb-1">{{ $person->user->name }}</span>@empty Unassigned @endforelse</dd><dt class="col-5">Due date</dt><dd class="col-7">{{ $task->due_date?->format('d M Y') ?? 'Not set' }}</dd><dt class="col-5">Assigned by</dt><dd class="col-7">{{ $task->assigner?->name ?? 'N/A' }}</dd></dl>
 </div></div>
</div>
@endsection
