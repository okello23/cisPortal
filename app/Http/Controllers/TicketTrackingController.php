<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

class TicketTrackingController extends Controller
{
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
            'reportUrl' => $ticket?->feedback?->incident_report_path
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

        abort_if(blank($ticket->feedback?->incident_report_path), 404);
        abort_unless(Storage::disk('local')->exists($ticket->feedback->incident_report_path), 404);

        return response(
            Storage::disk('local')->get($ticket->feedback->incident_report_path),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$ticket->ticket_number.'-incident-resolution-report.pdf"',
            ]
        );
    }
}
