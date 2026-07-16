<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Support\IncidentResolutionReportService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

class TicketTrackingController extends Controller
{
    public function __construct(
        private readonly IncidentResolutionReportService $incidentResolutionReportService,
    ) {
    }

    public function create(): View
    {
        return view('tickets.track');
    }

    public function search(Request $request): View
    {
        $validated = $request->validate([
            'ticket_number' => ['required', 'string'],
            'contact' => ['required', 'string'],
        ]);

        $ticket = Ticket::query()
            ->with(['status', 'assignedStaff', 'designation', 'statusLogs.newStatus', 'feedback', 'attachments'])
            ->where('ticket_number', $validated['ticket_number'])
            ->where(function ($query) use ($validated) {
                $query->where('email', $validated['contact'])
                    ->orWhere('phone', $validated['contact']);
            })
            ->first();

        return view('tickets.track', [
            'ticket' => $ticket,
            'reportUrl' => $ticket && ($ticket->resolved_at !== null || $ticket->closed_at !== null)
                ? URL::temporarySignedRoute(
                    'tickets.report.download',
                    now()->addHours(4),
                    ['ticket' => $ticket]
                )
                : null,
        ]);
    }

    public function downloadIncidentResolutionReport(Request $request, Ticket $ticket): Response
    {
        abort_unless($request->hasValidSignature(), 403);

        $ticket->load('feedback');

        abort_if($ticket->resolved_at === null && $ticket->closed_at === null, 404);

        if ($ticket->feedback) {
            $path = $this->incidentResolutionReportService->generateForFeedback($ticket, $ticket->feedback);

            $ticket->feedback->forceFill([
                'incident_report_path' => $path,
                'incident_report_generated_at' => now(),
            ])->save();
        } else {
            $path = $this->incidentResolutionReportService->generateForTicket($ticket);
        }

        return response(
            Storage::disk('local')->get($path),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$ticket->ticket_number.'-incident-resolution-report.pdf"',
            ]
        );
    }
}
