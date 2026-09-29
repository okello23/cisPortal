<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class EmailConnectivityTestMail extends Mailable
{
    public function envelope(): Envelope
    {
        return new Envelope(subject: 'CPHL Support Portal email test');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.email-connectivity-test');
    }
}
