<?php

namespace App\Http\Controllers;

use App\Models\NutritionTask;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PublicNutritionTaskController extends Controller
{
    public function create(): View
    {
        return view('nutrition-tasks.create', [
            'types' => NutritionTask::TYPES,
            'priorities' => NutritionTask::PRIORITIES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'entry_date' => ['required', 'date', 'before_or_equal:today'],
            'requestor_designation' => ['required', 'string', 'max:255'],
            'requestor_place_of_work' => ['required', 'string', 'max:255'],
            'requestor_name' => ['required', 'string', 'max:255'],
            'requestor_email' => ['required', 'email', 'max:255'],
            'requestor_phone' => ['required', 'string', 'max:50'],
            'request_type' => ['required', Rule::in(array_keys(NutritionTask::TYPES))],
            'other_request_type' => ['nullable', 'required_if:request_type,other', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
            'requested_assignee_email' => ['nullable', 'email', 'max:255'],
            'requested_assignee_name' => ['nullable', 'string', 'max:255'],
            'priority' => ['required', Rule::in(array_keys(NutritionTask::PRIORITIES))],
        ]);

        $task = DB::transaction(function () use ($validated) {
            $task = NutritionTask::query()->create([
                ...$validated,
                'task_number' => 'NT-'.now()->format('ymd').'-'.str_pad((string) (NutritionTask::query()->whereDate('created_at', today())->lockForUpdate()->count() + 1), 3, '0', STR_PAD_LEFT),
                'status' => 'not_started',
            ]);

            $task->histories()->create(['note' => 'Task request submitted through the public form.']);

            return $task;
        });

        return redirect()->route('nutrition-tasks.create')->with(
            'status',
            "Task request {$task->task_number} was submitted successfully. Please keep this reference number."
        );
    }
}
