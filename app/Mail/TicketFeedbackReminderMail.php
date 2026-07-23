<?php

namespace App\Mail;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TicketFeedbackReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Ticket $ticket)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Reminder: please rate support for ticket '.$this->ticket->ticket_number);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.ticket-feedback-reminder', with: [
            'feedbackUrl' => $this->ticket->absoluteFeedbackUrl(),
        ]);
    }
}
