<h2>Nutrition task due-date reminder</h2>
<p>Hello {{ $task->assignee->user->name }},</p>
<p>{{ $task->task_number }} is due on <strong>{{ $task->due_date->format('d F Y') }}</strong> and is currently {{ \App\Models\NutritionTask::STATUSES[$task->status] ?? $task->status }}.</p>
<p><a href="{{ route('nutrition-tasks.show', $task) }}">Review and update task progress</a></p>
