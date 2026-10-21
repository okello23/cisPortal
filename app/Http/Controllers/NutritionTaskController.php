<?php

namespace App\Http\Controllers;

use App\Mail\NutritionSubtaskAssignedMail;
use App\Mail\NutritionTaskAssignedMail;
use App\Models\NutritionSubtask;
use App\Models\NutritionTask;
use App\Models\NutritionTeamUser;
use App\Models\User;
use App\Support\OutboundMail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class NutritionTaskController extends Controller
{
    public function __construct(private readonly OutboundMail $outboundMail) {}

    public function dashboard(Request $request): View
    {
        $user = $request->user(); $this->authorizeNutritionAccess($user);
        $isTaskAdmin = $user->role === User::ROLE_TASK_ADMIN;
        $base = NutritionTask::query();
        if (! $isTaskAdmin) $this->scopeForMember($base, $user->nutritionProfile?->id ?? 0);
        $tab = $request->string('tab')->toString();
        $activeTab = $isTaskAdmin && in_array($tab, ['performance', 'manager'], true) ? $tab : 'overview';
        $metrics = collect(NutritionTask::STATUSES)->mapWithKeys(fn ($label, $status) => [$status => (clone $base)->where('status', $status)->count()])->all();
        $metrics['total'] = (clone $base)->count();
        $metrics['overdue'] = (clone $base)->where('status', '!=', 'completed')->whereDate('due_date', '<', today())->count();
        $performance = $isTaskAdmin ? NutritionTeamUser::query()->with('user')->get()->map(function ($profile) {
            $tasks = $profile->assignedTasks(); $completed = (clone $tasks)->where('status', 'completed')->count(); $total = (clone $tasks)->count();
            return ['name' => $profile->user->name, 'total' => $total, 'active' => (clone $tasks)->where('status', '!=', 'completed')->count(),
                'completed' => $completed, 'overdue' => (clone $tasks)->where('status', '!=', 'completed')->whereDate('due_date', '<', today())->count(),
                'completion_rate' => $total ? round(($completed / $total) * 100, 1) : 0];
        }) : collect();
        return view('nutrition-tasks.dashboard', ['activeTab' => $activeTab, 'isTaskAdmin' => $isTaskAdmin, 'metrics' => $metrics,
            'recentTasks' => (clone $base)->with('assignees.user')->latest()->limit(12)->get(), 'performance' => $performance,
            'prioritySummary' => $isTaskAdmin ? NutritionTask::query()->selectRaw('priority, count(*) as total')->groupBy('priority')->pluck('total', 'priority') : collect(),
            'typeSummary' => $isTaskAdmin ? NutritionTask::query()->selectRaw('request_type, count(*) as total')->groupBy('request_type')->pluck('total', 'request_type') : collect()]);
    }

    public function index(Request $request): View
    {
        $user = $request->user(); $this->authorizeNutritionAccess($user); $isTaskAdmin = $user->role === User::ROLE_TASK_ADMIN;
        $tasks = NutritionTask::query()->with('assignees.user')
            ->when(! $isTaskAdmin, fn ($q) => $this->scopeForMember($q, $user->nutritionProfile?->id ?? 0))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('priority'), fn ($q) => $q->where('priority', $request->string('priority')))
            ->when($request->filled('search'), function ($q) use ($request) { $term = $request->string('search')->toString();
                $q->where(fn ($n) => $n->where('task_number', 'like', "%{$term}%")->orWhere('requestor_name', 'like', "%{$term}%")->orWhere('description', 'like', "%{$term}%"));
            })->latest()->paginate(15)->withQueryString();
        return view('nutrition-tasks.index', compact('tasks', 'isTaskAdmin') + ['statuses' => NutritionTask::STATUSES, 'priorities' => NutritionTask::PRIORITIES]);
    }

    public function show(Request $request, NutritionTask $nutritionTask): View
    {
        $this->authorizeTaskAccess($request->user(), $nutritionTask);
        return view('nutrition-tasks.show', ['task' => $nutritionTask->load(['assignees.user', 'assigner', 'subtasks.assignees.user', 'histories.changedBy', 'histories.oldAssignee.user', 'histories.newAssignee.user']),
            'teamMembers' => NutritionTeamUser::query()->with('user')->whereHas('user', fn ($q) => $q->where('active', true))->get()->sortBy('user.name'),
            'statuses' => NutritionTask::STATUSES, 'isTaskAdmin' => $request->user()->role === User::ROLE_TASK_ADMIN,
            'canUpdateMainTask' => $request->user()->role === User::ROLE_TASK_ADMIN || $nutritionTask->assignees()->whereKey($request->user()->nutritionProfile?->id ?? 0)->exists()]);
    }

    public function update(Request $request, NutritionTask $nutritionTask): RedirectResponse
    {
        $user = $request->user(); $this->authorizeMainTaskUpdate($user, $nutritionTask); $admin = $user->role === User::ROLE_TASK_ADMIN;
        $rules = ['status' => ['required', Rule::in(array_keys(NutritionTask::STATUSES))], 'progress_notes' => ['nullable', 'string', 'max:5000']];
        if ($admin) $rules += ['assignee_ids' => ['nullable', 'array'], 'assignee_ids.*' => ['integer', 'distinct', 'exists:nutrition_team_users,id'], 'due_date' => ['nullable', 'date']];
        $data = $request->validate($rules);
        if ($data['status'] === 'completed' && $nutritionTask->subtasks()->where('status', '!=', 'completed')->exists())
            return back()->withErrors(['status' => 'Complete all subtasks before completing the main task.'])->withInput();
        $oldStatus = $nutritionTask->status; $oldIds = $nutritionTask->assignees()->pluck('nutrition_team_users.id')->map(fn ($id) => (int) $id)->all();
        $newIds = $admin ? array_values(array_unique($data['assignee_ids'] ?? [])) : $oldIds;
        DB::transaction(function () use ($nutritionTask, $user, $data, $admin, $oldStatus, $oldIds, $newIds) {
            $nutritionTask->status = $data['status']; $nutritionTask->progress_notes = $data['progress_notes'] ?? $nutritionTask->progress_notes;
            if ($admin) { $nutritionTask->assignees()->sync($newIds); $nutritionTask->assigned_to = $newIds[0] ?? null; $nutritionTask->due_date = $data['due_date'] ?? null;
                if ($oldIds !== $newIds) { $nutritionTask->assigned_by = $user->id; $nutritionTask->assigned_at = now(); $nutritionTask->last_reminder_sent_at = null; } }
            $nutritionTask->completed_at = $nutritionTask->status === 'completed' ? ($nutritionTask->completed_at ?? now()) : null; $nutritionTask->save();
            $nutritionTask->histories()->create(['changed_by' => $user->id, 'old_status' => $oldStatus, 'new_status' => $nutritionTask->status,
                'old_assignee_id' => $oldIds[0] ?? null, 'new_assignee_id' => $newIds[0] ?? null, 'note' => $data['progress_notes'] ?? null]);
        });
        $failed = $this->notifyMain($nutritionTask, array_values(array_diff($newIds, $oldIds)));
        return back()->with($failed ? 'warning' : 'status', $failed ? 'Task changes were saved, but one or more assignment emails were not sent.' : 'Task updated successfully.');
    }

    public function storeSubtask(Request $request, NutritionTask $nutritionTask): RedirectResponse
    {
        $this->authorizeNutritionAccess($request->user()); abort_unless($request->user()->role === User::ROLE_TASK_ADMIN, 403);
        $data = $request->validate(['title' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:5000'], 'due_date' => ['nullable', 'date'],
            'assignee_ids' => ['nullable', 'array'], 'assignee_ids.*' => ['integer', 'distinct', 'exists:nutrition_team_users,id']]);
        $subtask = DB::transaction(function () use ($nutritionTask, $request, $data) { $subtask = $nutritionTask->subtasks()->create($data + ['created_by' => $request->user()->id]); $subtask->assignees()->sync($data['assignee_ids'] ?? []); return $subtask; });
        $failed = $this->notifySubtask($subtask);
        return back()->with($failed ? 'warning' : 'status', $failed ? 'Subtask was created, but one or more assignment emails were not sent.' : 'Subtask created successfully.');
    }

    public function updateSubtask(Request $request, NutritionTask $nutritionTask, NutritionSubtask $nutritionSubtask): RedirectResponse
    {
        abort_unless($nutritionSubtask->nutrition_task_id === $nutritionTask->id, 404); $user = $request->user(); $this->authorizeSubtaskUpdate($user, $nutritionSubtask); $admin = $user->role === User::ROLE_TASK_ADMIN;
        $rules = ['status' => ['required', Rule::in(array_keys(NutritionTask::STATUSES))]];
        if ($admin) $rules += ['title' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:5000'], 'due_date' => ['nullable', 'date'], 'assignee_ids' => ['nullable', 'array'], 'assignee_ids.*' => ['integer', 'distinct', 'exists:nutrition_team_users,id']];
        $data = $request->validate($rules); $oldIds = $nutritionSubtask->assignees()->pluck('nutrition_team_users.id')->map(fn ($id) => (int) $id)->all();
        if ($admin) { $nutritionSubtask->fill($data); $nutritionSubtask->assignees()->sync($data['assignee_ids'] ?? []); }
        $nutritionSubtask->status = $data['status']; $nutritionSubtask->completed_at = $data['status'] === 'completed' ? ($nutritionSubtask->completed_at ?? now()) : null; $nutritionSubtask->save();
        $newIds = $nutritionSubtask->assignees()->pluck('nutrition_team_users.id')->map(fn ($id) => (int) $id)->all();
        $failed = $admin && $oldIds !== $newIds ? $this->notifySubtask($nutritionSubtask, array_values(array_diff($newIds, $oldIds))) : false;
        return back()->with($failed ? 'warning' : 'status', $failed ? 'Subtask changes were saved, but one or more assignment emails were not sent.' : 'Subtask updated successfully.');
    }

    private function scopeForMember(Builder $q, int $id): Builder { return $q->where(fn ($n) => $n->whereHas('assignees', fn ($a) => $a->whereKey($id))->orWhereHas('subtasks.assignees', fn ($a) => $a->whereKey($id))); }
    private function notifyMain(NutritionTask $task, array $ids): bool { $failed = false; foreach ($task->assignees()->with('user')->whereKey($ids)->get() as $person) $failed = ! $this->outboundMail->send($person->user->email, new NutritionTaskAssignedMail($task, $person)) || $failed; return $failed; }
    private function notifySubtask(NutritionSubtask $subtask, ?array $ids = null): bool { $subtask->load(['task.assignees.user', 'assignees.user']); $people = $subtask->effectiveAssignees(); if ($ids !== null) $people = $people->whereIn('id', $ids); $failed = false; foreach ($people->unique('id') as $person) $failed = ! $this->outboundMail->send($person->user->email, new NutritionSubtaskAssignedMail($subtask, $person)) || $failed; return $failed; }
    private function authorizeNutritionAccess(User $user): void { abort_unless($user->isNutritionUser(), 403); }
    private function authorizeTaskAccess(User $user, NutritionTask $task): void { $this->authorizeNutritionAccess($user); $id = $user->nutritionProfile?->id ?? 0; abort_unless($user->role === User::ROLE_TASK_ADMIN || $task->assignees()->whereKey($id)->exists() || $task->subtasks()->whereHas('assignees', fn ($q) => $q->whereKey($id))->exists(), 403); }
    private function authorizeMainTaskUpdate(User $user, NutritionTask $task): void { $this->authorizeNutritionAccess($user); abort_unless($user->role === User::ROLE_TASK_ADMIN || $task->assignees()->whereKey($user->nutritionProfile?->id ?? 0)->exists(), 403); }
    private function authorizeSubtaskUpdate(User $user, NutritionSubtask $subtask): void { $this->authorizeNutritionAccess($user); if ($user->role === User::ROLE_TASK_ADMIN) return; $id = $user->nutritionProfile?->id ?? 0; $allowed = $subtask->assignees()->exists() ? $subtask->assignees()->whereKey($id)->exists() : $subtask->task->assignees()->whereKey($id)->exists(); abort_unless($allowed, 403); }
}
