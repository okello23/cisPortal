<?php

namespace App\Mail;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class TicketFeedbackThankYouMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Ticket $ticket)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Thank you for your feedback: '.$this->ticket->ticket_number);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.ticket-feedback-thank-you');
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $path = $this->ticket->feedback?->incident_report_path;

        if (! $path || ! Storage::disk('local')->exists($path)) {
            return [];
        }

        return [
            Attachment::fromStorageDisk('local', $path)
                ->as($this->ticket->ticket_number.'-resolution-report.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
