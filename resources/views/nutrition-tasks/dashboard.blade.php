@extends('layouts.app', ['title' => 'Nutrition Task Dashboard'])

@section('content')
<div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
    <div><p class="text-uppercase text-muted fw-semibold small mb-1">Nutrition Task Tracker</p><h1 class="h2 mb-0">Welcome, {{ auth()->user()->name }}</h1></div>
    <div class="d-flex gap-2"><a href="{{ route('nutrition-tasks.index') }}" class="btn btn-cis-orange rounded-pill px-4">View Tasks</a>@if($isTaskAdmin)<a href="{{ route('admin.users.index', ['category' => 'micronutrient']) }}" class="btn btn-outline-dark rounded-pill px-4">Manage Nutrition Team Users</a>@endif</div>
</div>
<div class="d-flex align-items-center gap-2 flex-wrap mb-4">
    <a href="{{ route('nutrition-tasks.dashboard') }}" class="btn rounded-pill px-4 {{ $activeTab === 'overview' && request('workspace') !== 'micronutrient' ? 'btn-success' : 'btn-outline-dark' }}">Overview</a>
    @if($isTaskAdmin)
        <a href="{{ route('nutrition-tasks.dashboard', ['tab'=>'themes']) }}" class="btn rounded-pill px-4 {{ $activeTab === 'themes' ? 'btn-success' : 'btn-outline-dark' }}">Manage Themes</a>
        <a href="{{ route('nutrition-tasks.dashboard', ['tab'=>'manager']) }}" class="btn rounded-pill px-4 {{ $activeTab === 'manager' ? 'btn-success' : 'btn-outline-dark' }}">Manager Dashboard</a>
    @endif
    @include('partials.workspace-tabs')
</div>

@if($activeTab === 'overview')
    @include('nutrition-tasks.project-brief')
    <div class="row g-3 mb-4">
        @foreach([['Total Tasks',$metrics['total'],'#123b5d'],['Not Started',$metrics['not_started'],'#52606d'],['Started',$metrics['started'],'#be8c2a'],['In Progress',$metrics['in_progress'],'#0d9488'],['Completed',$metrics['completed'],'#198754'],['Overdue',$metrics['overdue'],'#dc3545']] as [$label,$value,$color])
            <div class="col-6 col-lg-2"><div class="metric-card metric-card-accent p-3 h-100" style="--metric-accent:{{ $color }}"><div class="fs-3 fw-bold">{{ $value }}</div><div class="text-muted small">{{ $label }}</div></div></div>
        @endforeach
    </div>
    <div class="content-card bg-white p-4">
        <div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h5 mb-0">{{ $isTaskAdmin ? 'Recent Team Tasks' : 'My Recent Tasks' }}</h2><a href="{{ route('nutrition-tasks.index') }}" class="btn btn-sm btn-outline-dark rounded-pill">View All</a></div>
        <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Task</th><th>Request</th><th>Main Assignees</th><th>Priority</th><th>Status</th><th>Due</th><th></th></tr></thead><tbody>
        @forelse($recentTasks as $task)
            <tr><td><strong>{{ $task->task_number }}</strong><div class="small text-muted">{{ $task->entry_date->format('d M Y') }}</div></td><td>{{ $task->requestTypeLabel() }}</td><td>@forelse($task->assignees as $person)<span class="badge text-bg-light border text-dark me-1 mb-1">{{ $person->user->name }}</span>@empty<span class="text-muted">Unassigned</span>@endforelse</td><td>{{ \App\Models\NutritionTask::PRIORITIES[$task->priority] }}</td><td><span class="badge text-bg-{{ $task->status === 'completed' ? 'success' : ($task->status === 'in_progress' ? 'info' : 'secondary') }}">{{ \App\Models\NutritionTask::STATUSES[$task->status] }}</span></td><td>{{ $task->due_date?->format('d M Y') ?? 'Not set' }}</td><td><a href="{{ route('nutrition-tasks.show',$task) }}" class="btn btn-sm btn-outline-dark rounded-pill">Open</a></td></tr>
        @empty<tr><td colspan="7" class="text-center text-muted py-4">No tasks available.</td></tr>@endforelse
        </tbody></table></div>
    </div>
@elseif($activeTab === 'themes')
    @include('nutrition-tasks.themes')
@elseif($activeTab === 'manager')
    @include('nutrition-tasks.theme-manager')
@endif
@endsection
