<h2>Customer Feedback Received for {{ $ticket->ticket_number }}</h2>
<p><strong>Requestor:</strong> {{ $ticket->full_name }}</p>
<p><strong>System:</strong> {{ $ticket->system->name }}</p>
<p><strong>Timeliness:</strong> {{ $ticket->feedback?->timeliness_rating }}/5</p>
<p><strong>Completeness:</strong> {{ $ticket->feedback?->completeness_rating }}/5</p>
<p><strong>Overall Satisfaction:</strong> {{ $ticket->feedback?->overall_satisfaction_rating }}/5</p>
<p><strong>Average Rating:</strong> {{ number_format(collect([
    $ticket->feedback?->timeliness_rating,
    $ticket->feedback?->completeness_rating,
    $ticket->feedback?->overall_satisfaction_rating,
])->filter(fn ($value) => $value !== null)->avg() ?? 0, 1) }}/5</p>
<p><strong>Comments:</strong> {{ $ticket->feedback?->comments ?: 'No additional comments provided.' }}</p>
