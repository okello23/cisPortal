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
        'submitted_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }
}
