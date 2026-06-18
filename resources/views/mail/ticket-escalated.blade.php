<h2>Escalated Ticket: {{ $ticket->ticket_number }}</h2>
<p>This ticket has been escalated to you for further action.</p>
<p><strong>Escalated By:</strong> {{ $escalatedBy->name }}</p>
<p><strong>Reporter:</strong> {{ $ticket->full_name }}</p>
<p><strong>System:</strong> {{ $ticket->system->name }}</p>
<p><strong>Priority:</strong> {{ $ticket->priorityLevel?->name ?? 'N/A' }}</p>
<p><strong>Status:</strong> {{ $ticket->status->name }}</p>
<p><strong>Description:</strong> {{ $ticket->description }}</p>
<p><strong>Current Resolution Summary:</strong> {{ $ticket->resolution_summary ?? 'No resolution summary has been recorded yet.' }}</p>
