<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketFeedback extends Model
{
    protected $fillable = [
        'ticket_id',
        'timeliness_rating',
        'completeness_rating',
        'overall_satisfaction_rating',
        'comments',
        'incident_report_path',
        'incident_report_generated_at',
        'submitted_at',
    ];

    protected $casts = [
        'incident_report_generated_at' => 'datetime',
        'submitted_at' => 'datetime',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }
}
