<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\URL;

class TicketAttachment extends Model
{
    protected $fillable = [
        'ticket_id',
        'storage_disk',
        'storage_path',
        'original_filename',
        'detected_mime_type',
        'extension',
        'file_size',
        'checksum',
        'status',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function scans(): HasMany
    {
        return $this->hasMany(AttachmentSecurityScan::class);
    }

    public function downloadUrl(): string
    {
        return URL::signedRoute('tickets.attachments.show', ['attachment' => $this]);
    }

    public function previewUrl(): string
    {
        return URL::signedRoute('tickets.attachments.preview', ['attachment' => $this]);
    }

    public function isPreviewableImage(): bool
    {
        return in_array($this->extension, ['jpg', 'jpeg', 'png'], true);
    }

    public function isPreviewablePdf(): bool
    {
        return $this->extension === 'pdf';
    }
}
