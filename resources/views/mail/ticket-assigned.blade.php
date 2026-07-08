<h2>Your ticket {{ $ticket->ticket_number }} has been assigned.</h2>
<p><strong>Assigned Staff:</strong> {{ $ticket->assignedStaff?->name }}</p>
<p><strong>Email:</strong> {{ $ticket->assignedStaff?->email ?? 'N/A' }}</p>
<p><strong>Phone:</strong> {{ $ticket->assignedStaff?->phone ?? 'N/A' }}</p>
<p><strong>Expected Resolution Date:</strong> {{ optional($ticket->expected_resolution_date)->format('d F Y') ?? 'Pending' }}</p>
