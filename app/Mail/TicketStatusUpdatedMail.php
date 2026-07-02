<?php

namespace App\Mail;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class TicketStatusUpdatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Ticket $ticket)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Ticket update: '.$this->ticket->ticket_number);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.ticket-status-updated', with: [
            'feedbackUrl' => $this->ticket->canReceiveFeedback()
                ? URL::signedRoute('tickets.feedback.show', ['ticket' => $this->ticket])
                : null,
        ]);
    }
}
