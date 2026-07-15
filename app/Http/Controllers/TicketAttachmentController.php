<?php

namespace App\Http\Controllers;

use App\Models\TicketAttachment;
use App\Support\AttachmentSecurityService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TicketAttachmentController extends Controller
{
    public function __construct(
        private readonly AttachmentSecurityService $attachmentSecurityService,
    ) {
    }

    public function show(TicketAttachment $attachment): StreamedResponse
    {
        return $this->attachmentSecurityService->streamDownload($attachment);
    }

    public function preview(TicketAttachment $attachment): StreamedResponse
    {
        abort_unless($attachment->isPreviewableImage(), 404);

        return $this->attachmentSecurityService->streamInline($attachment);
    }
}
