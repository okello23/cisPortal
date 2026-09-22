@extends('layouts.app', ['title' => 'Nutrition Tasks'])

@section('content')
<div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4"><div><p class="text-uppercase text-muted fw-semibold small mb-1">Nutrition Task Tracker</p><h1 class="h2 mb-0">{{ $isTaskAdmin ? 'All Task Requests' : 'My Tasks' }}</h1></div><a href="{{ route('nutrition-tasks.dashboard') }}" class="btn btn-outline-dark rounded-pill px-4">Dashboard</a></div>
<div class="content-card bg-white p-4">
    <form class="row g-2 mb-4" method="GET"><div class="col-md-5"><input name="search" value="{{ request('search') }}" class="form-control" placeholder="Search task number, requestor, or description"></div><div class="col-md-3"><select name="status" class="form-select"><option value="">All statuses</option>@foreach($statuses as $value=>$label)<option value="{{ $value }}" @selected(request('status')===$value)>{{ $label }}</option>@endforeach</select></div><div class="col-md-2"><select name="priority" class="form-select"><option value="">All priorities</option>@foreach($priorities as $value=>$label)<option value="{{ $value }}" @selected(request('priority')===$value)>{{ $label }}</option>@endforeach</select></div><div class="col-md-2"><button class="btn btn-dark w-100">Filter</button></div></form>
    <div class="table-responsive"><table class="table align-middle table-striped"><thead><tr><th>Task</th><th>Requestor</th><th>Type</th><th>Assignee</th><th>Priority</th><th>Status</th><th>Due</th><th></th></tr></thead><tbody>
    @forelse($tasks as $task)<tr><td class="fw-semibold">{{ $task->task_number }}</td><td>{{ $task->requestor_name }}<div class="small text-muted">{{ $task->requestor_place_of_work }}</div></td><td>{{ $task->requestTypeLabel() }}</td><td>{{ $task->assignee?->user?->name ?? 'Unassigned' }}</td><td>{{ $priorities[$task->priority] }}</td><td>{{ $statuses[$task->status] }}</td><td class="{{ $task->due_date?->isPast() && $task->status !== 'completed' ? 'text-danger fw-semibold' : '' }}">{{ $task->due_date?->format('d M Y') ?? 'Not set' }}</td><td><a href="{{ route('nutrition-tasks.show',$task) }}" class="btn btn-sm btn-outline-dark rounded-pill">Open</a></td></tr>
    @empty<tr><td colspan="8" class="text-center text-muted py-4">No tasks match the selected filters.</td></tr>@endforelse
    </tbody></table></div>{{ $tasks->links() }}
</div>
@endsection
