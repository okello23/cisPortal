<h2>Nutrition subtask due-date reminder</h2>
<p>Hello {{ $recipient->user->name }},</p>
<p><strong>{{ $subtask->title }}</strong> under {{ $subtask->task->task_number }} is due on <strong>{{ $subtask->due_date->format('d F Y') }}</strong>.</p>
<p><a href="{{ route('nutrition-tasks.show', $subtask->task) }}">Review and update the subtask</a></p>
