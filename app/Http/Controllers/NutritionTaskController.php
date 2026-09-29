<?php

namespace App\Http\Controllers;

use App\Mail\NutritionTaskAssignedMail;
use App\Models\NutritionTask;
use App\Models\NutritionTeamUser;
use App\Models\User;
use App\Support\OutboundMail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class NutritionTaskController extends Controller
{
    public function __construct(private readonly OutboundMail $outboundMail) {}

    public function dashboard(Request $request): View
    {
        $user = $request->user();
        $this->authorizeNutritionAccess($user);
        $isTaskAdmin = $user->role === User::ROLE_TASK_ADMIN;
        $base = NutritionTask::query();

        if (! $isTaskAdmin) {
            $base->where('assigned_to', $user->nutritionProfile?->id ?? 0);
        }

        $requestedTab = $request->string('tab')->toString();
        $activeTab = $isTaskAdmin && in_array($requestedTab, ['performance', 'manager'], true)
            ? $requestedTab
            : 'overview';

        $metrics = collect(NutritionTask::STATUSES)->mapWithKeys(
            fn ($label, $status) => [$status => (clone $base)->where('status', $status)->count()]
        )->all();
        $metrics['total'] = (clone $base)->count();
        $metrics['overdue'] = (clone $base)->where('status', '!=', 'completed')->whereDate('due_date', '<', today())->count();

        $performance = collect();
        if ($isTaskAdmin) {
            $performance = NutritionTeamUser::query()->with('user')->get()->map(function ($profile) {
                $tasks = $profile->tasks();
                $completed = (clone $tasks)->where('status', 'completed')->count();
                $total = (clone $tasks)->count();

                return [
                    'name' => $profile->user->name,
                    'total' => $total,
                    'active' => (clone $tasks)->where('status', '!=', 'completed')->count(),
                    'completed' => $completed,
                    'overdue' => (clone $tasks)->where('status', '!=', 'completed')->whereDate('due_date', '<', today())->count(),
                    'completion_rate' => $total > 0 ? round(($completed / $total) * 100, 1) : 0,
                ];
            });
        }

        return view('nutrition-tasks.dashboard', [
            'activeTab' => $activeTab,
            'isTaskAdmin' => $isTaskAdmin,
            'metrics' => $metrics,
            'recentTasks' => (clone $base)->with('assignee.user')->latest()->limit(12)->get(),
            'performance' => $performance,
            'prioritySummary' => $isTaskAdmin ? NutritionTask::query()->selectRaw('priority, count(*) as total')->groupBy('priority')->pluck('total', 'priority') : collect(),
            'typeSummary' => $isTaskAdmin ? NutritionTask::query()->selectRaw('request_type, count(*) as total')->groupBy('request_type')->pluck('total', 'request_type') : collect(),
        ]);
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $this->authorizeNutritionAccess($user);
        $isTaskAdmin = $user->role === User::ROLE_TASK_ADMIN;

        $tasks = NutritionTask::query()->with('assignee.user')
            ->when(! $isTaskAdmin, fn ($query) => $query->where('assigned_to', $user->nutritionProfile?->id ?? 0))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('priority'), fn ($query) => $query->where('priority', $request->string('priority')))
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = $request->string('search')->toString();
                $query->where(fn ($nested) => $nested->where('task_number', 'like', "%{$term}%")
                    ->orWhere('requestor_name', 'like', "%{$term}%")
                    ->orWhere('description', 'like', "%{$term}%"));
            })
            ->latest()->paginate(15)->withQueryString();

        return view('nutrition-tasks.index', compact('tasks', 'isTaskAdmin') + [
            'statuses' => NutritionTask::STATUSES,
            'priorities' => NutritionTask::PRIORITIES,
        ]);
    }

    public function show(Request $request, NutritionTask $nutritionTask): View
    {
        $this->authorizeTask($request->user(), $nutritionTask);

        return view('nutrition-tasks.show', [
            'task' => $nutritionTask->load(['assignee.user', 'assigner', 'histories.changedBy', 'histories.oldAssignee.user', 'histories.newAssignee.user']),
            'teamMembers' => NutritionTeamUser::query()->with('user')->whereHas('user', fn ($query) => $query->where('active', true))->get()->sortBy('user.name'),
            'statuses' => NutritionTask::STATUSES,
            'isTaskAdmin' => $request->user()->role === User::ROLE_TASK_ADMIN,
        ]);
    }

    public function update(Request $request, NutritionTask $nutritionTask): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeTask($user, $nutritionTask);
        $isTaskAdmin = $user->role === User::ROLE_TASK_ADMIN;

        $rules = [
            'status' => ['required', Rule::in(array_keys(NutritionTask::STATUSES))],
            'progress_notes' => ['nullable', 'string', 'max:5000'],
        ];
        if ($isTaskAdmin) {
            $rules['assigned_to'] = ['nullable', 'exists:nutrition_team_users,id'];
            $rules['due_date'] = ['nullable', 'date'];
        }
        $validated = $request->validate($rules);

        $oldStatus = $nutritionTask->status;
        $oldAssignee = $nutritionTask->assigned_to;
        $nutritionTask->status = $validated['status'];
        $nutritionTask->progress_notes = $validated['progress_notes'] ?? $nutritionTask->progress_notes;

        if ($isTaskAdmin) {
            $nutritionTask->assigned_to = $validated['assigned_to'] ?? null;
            $nutritionTask->due_date = $validated['due_date'] ?? null;
            if ($oldAssignee !== $nutritionTask->assigned_to) {
                $nutritionTask->assigned_by = $user->id;
                $nutritionTask->assigned_at = now();
                $nutritionTask->last_reminder_sent_at = null;
            }
        }

        $nutritionTask->completed_at = $nutritionTask->status === 'completed' ? ($nutritionTask->completed_at ?? now()) : null;
        $nutritionTask->save();
        $nutritionTask->histories()->create([
            'changed_by' => $user->id,
            'old_status' => $oldStatus,
            'new_status' => $nutritionTask->status,
            'old_assignee_id' => $oldAssignee,
            'new_assignee_id' => $nutritionTask->assigned_to,
            'note' => $validated['progress_notes'] ?? null,
        ]);

        if ($oldAssignee !== $nutritionTask->assigned_to && $nutritionTask->assigned_to) {
            $nutritionTask->load('assignee.user');
            if (! $this->outboundMail->send($nutritionTask->assignee->user->email, new NutritionTaskAssignedMail($nutritionTask))) {
                return back()->with('warning', 'Task assignment was saved, but the assignee email was not sent. Check SMTP configuration and notify the assignee directly.');
            }
        }

        return back()->with('status', 'Task updated successfully.');
    }

    private function authorizeNutritionAccess(User $user): void
    {
        abort_unless($user->isNutritionUser(), 403);
    }

    private function authorizeTask(User $user, NutritionTask $task): void
    {
        $this->authorizeNutritionAccess($user);
        abort_unless($user->role === User::ROLE_TASK_ADMIN || $task->assigned_to === $user->nutritionProfile?->id, 403);
    }
}
