<h2>Ticket Assigned: {{ $ticket->ticket_number }}</h2>
<p>You have been assigned a new ICT support ticket.</p>
<p><strong>Reporter:</strong> {{ $ticket->full_name }}</p>
<p><strong>System:</strong> {{ $ticket->system->name }}</p>
<p><strong>Priority:</strong> {{ $ticket->priorityLevel?->name ?? 'N/A' }}</p>
<p><strong>Status:</strong> {{ $ticket->status->name }}</p>
<p><strong>Description:</strong> {{ $ticket->description }}</p>
<p><strong>Expected Resolution Date:</strong> {{ optional($ticket->expected_resolution_date)->format('d F Y') ?? 'Pending' }}</p>
