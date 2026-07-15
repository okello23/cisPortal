<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttachmentSecurityScan extends Model
{
    protected $fillable = [
        'ticket_attachment_id',
        'status',
        'scanner',
        'scan_result',
        'scanned_at',
    ];

    protected $casts = [
        'scanned_at' => 'datetime',
    ];

    public function attachment(): BelongsTo
    {
        return $this->belongsTo(TicketAttachment::class, 'ticket_attachment_id');
    }
}
