<?php

namespace App\Mail;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\SerializesModels;

class NewTicketAlertMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Ticket $ticket)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'New ICT Support Ticket: '.$this->ticket->ticket_number);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.new-ticket-alert');
    }
}
