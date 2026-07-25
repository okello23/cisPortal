<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketAiMessage extends Model
{
    protected $fillable = [
        'ticket_id',
        'user_id',
        'role',
        'content',
        'attachment_ids',
        'provider',
        'model',
        'provider_response_id',
        'metadata',
    ];

    protected $casts = [
        'attachment_ids' => 'array',
        'metadata' => 'array',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
