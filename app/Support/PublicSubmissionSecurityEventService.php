<?php

namespace App\Support;

use App\Models\PublicSubmissionSecurityEvent;
use App\Models\Ticket;
use Illuminate\Http\Request;

class PublicSubmissionSecurityEventService
{
    public function log(
        string $eventType,
        Request $request,
        ?string $submissionUuid = null,
        ?string $email = null,
        ?int $riskScore = null,
        array $metadata = [],
        ?string $actionTaken = null,
        ?Ticket $ticket = null,
        ?int $reviewingUserId = null,
    ): void {
        PublicSubmissionSecurityEvent::query()->create([
            'event_type' => $eventType,
            'submission_uuid' => $submissionUuid,
            'ticket_id' => $ticket?->id,
            'source_ip' => $request->ip(),
            'requestor_email_hash' => filled($email) ? hash('sha256', mb_strtolower(trim($email))) : null,
            'user_agent' => (string) $request->userAgent(),
            'risk_score' => $riskScore,
            'metadata' => $metadata,
            'action_taken' => $actionTaken,
            'reviewing_user_id' => $reviewingUserId,
        ]);
    }
}
