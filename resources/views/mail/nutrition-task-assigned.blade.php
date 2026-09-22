<h2>A Nutrition task has been assigned to you</h2>
<p>Hello {{ $task->assignee->user->name }},</p>
<p><strong>Task:</strong> {{ $task->task_number }}</p>
<p><strong>Request type:</strong> {{ $task->requestTypeLabel() }}</p>
<p><strong>Priority:</strong> {{ \App\Models\NutritionTask::PRIORITIES[$task->priority] ?? ucfirst($task->priority) }}</p>
<p><strong>Due date:</strong> {{ $task->due_date?->format('d F Y') ?? 'Not set' }}</p>
<p>{{ $task->description }}</p>
<p><a href="{{ route('nutrition-tasks.show', $task) }}">Open task</a></p>
