<h2>Ticket Update: {{ $ticket->ticket_number }}</h2>
<p><strong>Current Status:</strong> {{ $ticket->status->name }}</p>
<p><strong>Assigned Staff:</strong> {{ $ticket->assignedStaff?->name ?? 'Pending assignment' }}</p>
<p><strong>Resolution Summary:</strong> {{ $ticket->resolution_summary ?? 'We will share more details as work progresses.' }}</p>
@if ($feedbackUrl)
    <p>Your issue has been marked as resolved. <a href="{{ $feedbackUrl }}">Rate the support you received</a>.</p>
@endif
