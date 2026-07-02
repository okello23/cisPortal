<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use Illuminate\Http\Request;
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
            ->with(['status', 'assignedStaff', 'designation', 'statusLogs.newStatus', 'feedback'])
            ->where('ticket_number', $validated['ticket_number'])
            ->where(function ($query) use ($validated) {
                $query->where('email', $validated['contact'])
                    ->orWhere('phone', $validated['contact']);
            })
            ->first();

        return view('tickets.track', ['ticket' => $ticket]);
    }
}
