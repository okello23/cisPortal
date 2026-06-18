<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UserAccountCreatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user, public string $password)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your CPHL ICT Support Portal account');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.user-account-created');
    }
}
