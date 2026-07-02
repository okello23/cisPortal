<h2>Reminder: Please rate the support for ticket {{ $ticket->ticket_number }}</h2>
<p>Your ticket was marked as resolved more than 3 days ago.</p>
<p><strong>Assigned Staff:</strong> {{ $ticket->assignedStaff?->name ?? 'N/A' }}</p>
<p><strong>System:</strong> {{ $ticket->system->name }}</p>
<p><strong>Resolved On:</strong> {{ $ticket->resolved_at?->format('d M Y H:i') ?? 'N/A' }}</p>
<p><strong>Resolution Summary:</strong> {{ $ticket->resolution_summary ?? 'No resolution summary was provided.' }}</p>
<p>Please review the resolution and share your rating or follow up if you still need help.</p>
<p><a href="{{ $feedbackUrl }}">Open the feedback form</a></p>
<p>If you still need help, you can also <a href="{{ route('tickets.track') }}">track your ticket</a>.</p>
