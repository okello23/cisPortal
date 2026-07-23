<?php

namespace App\Http\Controllers;

use App\Mail\TicketFeedbackReceivedMail;
use App\Mail\TicketFeedbackThankYouMail;
use App\Models\Ticket;
use App\Models\TicketFeedback;
use App\Models\TicketStatusLog;
use App\Models\TicketStatus;
use App\Support\AuditService;
use App\Support\IncidentResolutionReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class TicketFeedbackController extends Controller
{
    public function __construct(
        private readonly AuditService $auditService,
        private readonly IncidentResolutionReportService $incidentResolutionReportService,
    ) {}

    public function create(Request $request, Ticket $ticket): View
    {
        abort_unless($request->hasValidSignature(), 403);

        return view('tickets.feedback', [
            'ticket' => $ticket->load(['status', 'assignedStaff', 'feedback', 'system']),
        ]);
    }

    public function store(Request $request, Ticket $ticket): RedirectResponse
    {
        abort_unless($request->hasValidSignature(), 403);

        $ticket->load(['status', 'assignedStaff', 'feedback', 'system']);

        abort_if($ticket->feedback !== null, 409, 'Feedback has already been submitted for this ticket.');

        $validated = $request->validate([
            'timeliness_rating' => ['required', 'integer', 'between:0,5'],
            'completeness_rating' => ['required', 'integer', 'between:0,5'],
            'overall_satisfaction_rating' => ['required', 'integer', 'between:0,5'],
            'comments' => ['nullable', 'string', 'max:5000'],
        ]);

        $feedback = TicketFeedback::query()->create([
            'ticket_id' => $ticket->id,
            ...$validated,
            'submitted_at' => now(),
        ]);

        $closedStatus = TicketStatus::query()->where('code', 'closed')->first();
        $oldStatusId = $ticket->status_id;

        if ($closedStatus && $ticket->status?->code === 'resolved') {
            $ticket->forceFill([
                'status_id' => $closedStatus->id,
                'closed_at' => now(),
                'feedback_reminder_sent_at' => null,
            ])->save();

            TicketStatusLog::query()->create([
                'ticket_id' => $ticket->id,
                'old_status_id' => $oldStatusId,
                'new_status_id' => $closedStatus->id,
            ]);
        } else {
            $ticket->forceFill(['feedback_reminder_sent_at' => null])->save();
        }

        $ticket->loadMissing([
            'designation',
            'facility',
            'priorityLevel',
            'statusLogs.changedBy',
            'statusLogs.newStatus',
        ]);

        $feedback->forceFill([
            'incident_report_path' => $this->incidentResolutionReportService->generateForFeedback($ticket, $feedback),
            'incident_report_generated_at' => now(),
        ])->save();

        $this->auditService->log('ticket.feedback_submitted', $feedback, null, $feedback->toArray(), null, $request);

        if ($ticket->assignedStaff?->email) {
            rescue(fn () => Mail::to($ticket->assignedStaff->email)->send(new TicketFeedbackReceivedMail($ticket->fresh(['assignedStaff', 'feedback', 'system']))), report: false);
        }

        if ($ticket->email) {
            rescue(fn () => Mail::to($ticket->email)->send(new TicketFeedbackThankYouMail($ticket->fresh(['assignedStaff', 'feedback', 'system']))), report: false);
        }

        return redirect()
            ->to($ticket->feedbackUrl())
            ->with('status', 'Thank you. Your feedback has been submitted successfully.');
    }
}
