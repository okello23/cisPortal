<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TicketAttachmentController extends Controller
{
    public function show(Ticket $ticket): StreamedResponse
    {
        abort_unless($ticket->hasAttachment(), 404);

        $disk = Storage::disk('public');
        abort_unless($disk->exists($ticket->attachment_path), 404);

        return $disk->response($ticket->attachment_path, $ticket->attachmentFilename());
    }
}
