<h2>New ICT Support Ticket: {{ $ticket->ticket_number }}</h2>
<p><strong>User:</strong> {{ $ticket->full_name }}</p>
<p><strong>Designation:</strong> {{ $ticket->designation?->name ?? 'N/A' }}</p>
<p><strong>Lab Manager:</strong> {{ $ticket->lab_manager_name ?? 'N/A' }}</p>
<p><strong>Lab Manager Email:</strong> {{ $ticket->lab_manager_email ?? 'N/A' }}</p>
<p><strong>System:</strong> {{ $ticket->system->name }}</p>
<p><strong>District:</strong> {{ $ticket->district_name ?? 'N/A' }}</p>
<p><strong>Issue Began:</strong> {{ optional($ticket->issue_started_at)->format('d M Y') ?? 'N/A' }}</p>
<p><strong>Module:</strong> {{ $ticket->module?->name ?? 'N/A' }}</p>
<p><strong>Issue Type:</strong> {{ $ticket->issueType->name }}</p>
<p><strong>Priority:</strong> {{ $ticket->priorityLevel->name }}</p>
<p><strong>Description:</strong> {{ $ticket->description }}</p>
@if ($ticket->attachment_path)
    <p><strong>Attachment:</strong> {{ $ticket->attachment_path }}</p>
@endif
