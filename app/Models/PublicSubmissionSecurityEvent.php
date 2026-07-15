<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PublicSubmissionSecurityEvent extends Model
{
    protected $fillable = [
        'event_type',
        'submission_uuid',
        'ticket_id',
        'source_ip',
        'requestor_email_hash',
        'user_agent',
        'risk_score',
        'metadata',
        'action_taken',
        'reviewing_user_id',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];
}
