<h2>Reminder: Ticket {{ $ticket->ticket_number }} is still pending action</h2>
<p><strong>Assigned Staff:</strong> {{ $ticket->assignedStaff?->name ?? 'N/A' }}</p>
<p><strong>System:</strong> {{ $ticket->system->name }}</p>
<p><strong>Status:</strong> {{ $ticket->status->name }}</p>
<p><strong>Priority:</strong> {{ $ticket->priorityLevel?->name ?? 'N/A' }}</p>
<p><strong>Idle Time:</strong> {{ $ticket->last_worked_at?->diffForHumans(now(), ['parts' => 2, 'syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE]) ?? 'More than 24 hours' }}</p>
<p><strong>Last Worked On:</strong> {{ $ticket->last_worked_at?->format('d M Y H:i') ?? 'Unknown' }}</p>
<p><strong>Description:</strong> {{ $ticket->description }}</p>
