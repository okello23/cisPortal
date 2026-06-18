<?php

namespace App\Support;

use App\Mail\TicketReminderMail;
use App\Models\Ticket;
use App\Models\TicketStatus;
use Illuminate\Support\Facades\Mail;

class TicketReminderService
{
    public function __construct(private readonly TicketRecipientResolver $recipientResolver)
    {
    }

    public function sendStaleAssignmentReminders(): int
    {
        $closedStatusIds = TicketStatus::query()
            ->whereIn('code', ['resolved', 'closed'])
            ->pluck('id');

        $tickets = Ticket::query()
            ->with(['system', 'status', 'assignedStaff', 'priorityLevel'])
            ->whereNotNull('assigned_to')
            ->whereNotIn('status_id', $closedStatusIds)
            ->whereNotNull('last_worked_at')
            ->where('last_worked_at', '<=', now()->subDay())
            ->where(function ($query) {
                $query->whereNull('last_reminder_sent_at')
                    ->orWhereColumn('last_reminder_sent_at', '<', 'last_worked_at')
                    ->orWhere('last_reminder_sent_at', '<=', now()->subDay());
            })
            ->get();

        $sent = 0;

        foreach ($tickets as $ticket) {
            if (! $ticket->assignedStaff?->email) {
                continue;
            }

            $cc = $this->recipientResolver->reminderCcEmails($ticket->assignedStaff->email);

            rescue(function () use ($ticket, $cc) {
                $mailer = Mail::to($ticket->assignedStaff->email);

                if ($cc !== []) {
                    $mailer->cc($cc);
                }

                $mailer->send(new TicketReminderMail($ticket));
            }, report: false);

            $ticket->forceFill(['last_reminder_sent_at' => now()])->save();
            $sent++;
        }

        return $sent;
    }
}
