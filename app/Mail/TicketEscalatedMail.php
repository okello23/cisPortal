<?php

namespace App\Mail;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TicketEscalatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Ticket $ticket, public User $escalatedBy)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Ticket escalated to you: '.$this->ticket->ticket_number);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.ticket-escalated');
    }
}
