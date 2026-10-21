<h2>A Nutrition subtask has been assigned to you</h2>
<p>Hello {{ $recipient->user->name }},</p>
<p><strong>Main task:</strong> {{ $subtask->task->task_number }}</p>
<p><strong>Subtask:</strong> {{ $subtask->title }}</p>
<p><strong>Due date:</strong> {{ $subtask->due_date?->format('d F Y') ?? 'Not set' }}</p>
@if($subtask->description)<p>{{ $subtask->description }}</p>@endif
<p><a href="{{ route('nutrition-tasks.show', $subtask->task) }}">Open task</a></p>
