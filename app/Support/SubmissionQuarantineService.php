<?php

namespace App\Support;

use App\Models\Ticket;
use App\Models\TicketStatus;

class SubmissionQuarantineService
{
    public function apply(Ticket $ticket, array $risk): Ticket
    {
        $ticket->submission_risk_score = $risk['score'];
        $ticket->submission_risk_level = $risk['level'];
        $ticket->submission_risk_reasons = $risk['reasons'];
        $ticket->is_suspected_spam = in_array($risk['level'], ['HIGH', 'CRITICAL'], true);
        $ticket->submission_review_status = match ($risk['level']) {
            'HIGH', 'CRITICAL' => 'QUARANTINED',
            'MEDIUM' => 'FLAGGED',
            default => 'ACCEPTED',
        };

        if ($ticket->submission_review_status === 'QUARANTINED') {
            $ticket->quarantined_at = now();
            $ticket->quarantine_reason = collect($risk['reasons'])->pluck('rule')->implode(', ');
            $ticket->status_id = TicketStatus::query()->where('code', 'new')->value('id') ?? $ticket->status_id;
        } else {
            $ticket->released_to_queue_at = now();
        }

        $ticket->save();

        return $ticket;
    }
}
