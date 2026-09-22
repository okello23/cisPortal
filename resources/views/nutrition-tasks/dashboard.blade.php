@extends('layouts.app', ['title' => 'Nutrition Task Dashboard'])

@section('content')
<div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
    <div><p class="text-uppercase text-muted fw-semibold small mb-1">Nutrition Task Tracker</p><h1 class="h2 mb-0">Welcome, {{ auth()->user()->name }}</h1></div>
    <div class="d-flex gap-2"><a href="{{ route('nutrition-tasks.index') }}" class="btn btn-cis-orange rounded-pill px-4">View Tasks</a>@if($isTaskAdmin)<a href="{{ route('admin.nutrition-users.index') }}" class="btn btn-outline-dark rounded-pill px-4">Manage Nutrition Team Users</a>@endif</div>
</div>
<div class="d-flex gap-2 flex-wrap mb-4">
    <a href="{{ route('nutrition-tasks.dashboard') }}" class="btn rounded-pill px-4 {{ $activeTab === 'overview' ? 'btn-dark' : 'btn-outline-dark' }}">Overview</a>
    @if($isTaskAdmin)
        <a href="{{ route('nutrition-tasks.dashboard', ['tab'=>'performance']) }}" class="btn rounded-pill px-4 {{ $activeTab === 'performance' ? 'btn-warning' : 'btn-outline-warning' }}">Support Team Performance</a>
        <a href="{{ route('nutrition-tasks.dashboard', ['tab'=>'manager']) }}" class="btn rounded-pill px-4 {{ $activeTab === 'manager' ? 'btn-success' : 'btn-outline-success' }}">Manager Dashboard</a>
    @endif
</div>

@if($activeTab === 'overview')
    <div class="row g-3 mb-4">
        @foreach([['Total Tasks',$metrics['total'],'#123b5d'],['Not Started',$metrics['not_started'],'#52606d'],['Started',$metrics['started'],'#be8c2a'],['In Progress',$metrics['in_progress'],'#0d9488'],['Completed',$metrics['completed'],'#198754'],['Overdue',$metrics['overdue'],'#dc3545']] as [$label,$value,$color])
            <div class="col-6 col-lg-2"><div class="metric-card metric-card-accent p-3 h-100" style="--metric-accent:{{ $color }}"><div class="fs-3 fw-bold">{{ $value }}</div><div class="text-muted small">{{ $label }}</div></div></div>
        @endforeach
    </div>
    <div class="content-card bg-white p-4">
        <div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h5 mb-0">{{ $isTaskAdmin ? 'Recent Team Tasks' : 'My Recent Tasks' }}</h2><a href="{{ route('nutrition-tasks.index') }}" class="btn btn-sm btn-outline-dark rounded-pill">View All</a></div>
        <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Task</th><th>Request</th><th>Assignee</th><th>Priority</th><th>Status</th><th>Due</th><th></th></tr></thead><tbody>
        @forelse($recentTasks as $task)
            <tr><td><strong>{{ $task->task_number }}</strong><div class="small text-muted">{{ $task->entry_date->format('d M Y') }}</div></td><td>{{ $task->requestTypeLabel() }}</td><td>{{ $task->assignee?->user?->name ?? 'Unassigned' }}</td><td>{{ \App\Models\NutritionTask::PRIORITIES[$task->priority] }}</td><td><span class="badge text-bg-{{ $task->status === 'completed' ? 'success' : ($task->status === 'in_progress' ? 'info' : 'secondary') }}">{{ \App\Models\NutritionTask::STATUSES[$task->status] }}</span></td><td>{{ $task->due_date?->format('d M Y') ?? 'Not set' }}</td><td><a href="{{ route('nutrition-tasks.show',$task) }}" class="btn btn-sm btn-outline-dark rounded-pill">Open</a></td></tr>
        @empty<tr><td colspan="7" class="text-center text-muted py-4">No tasks available.</td></tr>@endforelse
        </tbody></table></div>
    </div>
@elseif($activeTab === 'performance')
    <div class="content-card bg-white p-4"><h2 class="h4">Support Team Performance</h2><p class="text-muted">Completion and workload summary for Nutrition team members.</p>
        <div class="table-responsive"><table class="table table-striped align-middle"><thead><tr><th>Team Member</th><th>Total</th><th>Active</th><th>Completed</th><th>Overdue</th><th>Completion Rate</th></tr></thead><tbody>
        @forelse($performance as $row)<tr><td class="fw-semibold">{{ $row['name'] }}</td><td>{{ $row['total'] }}</td><td>{{ $row['active'] }}</td><td>{{ $row['completed'] }}</td><td>{{ $row['overdue'] }}</td><td>{{ number_format($row['completion_rate'],1) }}%</td></tr>@empty<tr><td colspan="6" class="text-center text-muted">No team members available.</td></tr>@endforelse
        </tbody></table></div></div>
@else
    <div class="row g-4">
        <div class="col-lg-6"><fieldset class="app-fieldset h-100"><legend>Tasks by Priority</legend><table class="table mb-0">@foreach(\App\Models\NutritionTask::PRIORITIES as $key=>$label)<tr><td>{{ $label }}</td><td class="text-end fw-bold">{{ $prioritySummary[$key] ?? 0 }}</td></tr>@endforeach</table></fieldset></div>
        <div class="col-lg-6"><fieldset class="app-fieldset h-100"><legend>Tasks by Request Type</legend><table class="table mb-0">@foreach(\App\Models\NutritionTask::TYPES as $key=>$label)<tr><td>{{ $label }}</td><td class="text-end fw-bold">{{ $typeSummary[$key] ?? 0 }}</td></tr>@endforeach</table></fieldset></div>
        <div class="col-12"><div class="alert alert-info rounded-4 mb-0">Use the task list to assign requests, set due dates, monitor progress, and review the complete change history.</div></div>
    </div>
@endif
@endsection
