<h2>Thank you for your feedback on ticket {{ $ticket->ticket_number }}</h2>
<p>We appreciate you taking the time to rate the support you received.</p>
<p><strong>System:</strong> {{ $ticket->system->name }}</p>
<p><strong>Assigned Staff:</strong> {{ $ticket->assignedStaff?->name ?? 'N/A' }}</p>
<p><strong>Overall Satisfaction:</strong> {{ $ticket->feedback?->overall_satisfaction_rating }}/5</p>
<p>Your incident resolution report is attached to this email for your records.</p>
<p>If you need any further assistance, you can <a href="{{ route('tickets.track') }}">track your ticket here</a>.</p>
