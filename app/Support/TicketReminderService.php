<?php

namespace App\Support;

use App\Mail\TicketFeedbackReminderMail;
use App\Mail\TicketReminderMail;
use App\Models\Ticket;
use App\Models\TicketStatus;
use Illuminate\Support\Facades\Mail;

class TicketReminderService
{
    private const REMINDER_WINDOW_DAYS = 3;

    public function __construct(private readonly TicketRecipientResolver $recipientResolver)
    {
    }

    public function sendScheduledReminders(): array
    {
        return [
            'unresolved' => $this->sendUnresolvedTicketReminders(),
            'feedback' => $this->sendPendingFeedbackReminders(),
        ];
    }

    public function sendUnresolvedTicketReminders(): int
    {
        $closedStatusIds = TicketStatus::query()
            ->whereIn('code', ['resolved', 'closed'])
            ->pluck('id');

        $reminderThreshold = now()->subDays(self::REMINDER_WINDOW_DAYS);

        $tickets = Ticket::query()
            ->with(['system', 'status', 'assignedStaff', 'priorityLevel'])
            ->whereNotNull('assigned_to')
            ->whereNotIn('status_id', $closedStatusIds)
            ->whereNotNull('last_worked_at')
            ->where('last_worked_at', '<=', $reminderThreshold)
            ->where(function ($query) use ($reminderThreshold) {
                $query->whereNull('last_reminder_sent_at')
                    ->orWhereColumn('last_reminder_sent_at', '<', 'last_worked_at')
                    ->orWhere('last_reminder_sent_at', '<=', $reminderThreshold);
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

    public function sendPendingFeedbackReminders(): int
    {
        $resolvedStatusIds = TicketStatus::query()
            ->where('code', 'resolved')
            ->pluck('id');

        $reminderThreshold = now()->subDays(self::REMINDER_WINDOW_DAYS);

        $tickets = Ticket::query()
            ->with(['system', 'status', 'assignedStaff', 'feedback'])
            ->whereNotNull('email')
            ->whereIn('status_id', $resolvedStatusIds)
            ->whereNotNull('resolved_at')
            ->where('resolved_at', '<=', $reminderThreshold)
            ->whereDoesntHave('feedback')
            ->where(function ($query) use ($reminderThreshold) {
                $query->whereNull('feedback_reminder_sent_at')
                    ->orWhere('feedback_reminder_sent_at', '<=', $reminderThreshold);
            })
            ->get();

        $sent = 0;

        foreach ($tickets as $ticket) {
            if (! $ticket->email) {
                continue;
            }

            rescue(fn () => Mail::to($ticket->email)->send(new TicketFeedbackReminderMail($ticket)), report: false);

            $ticket->forceFill(['feedback_reminder_sent_at' => now()])->save();
            $sent++;
        }

        return $sent;
    }
}
