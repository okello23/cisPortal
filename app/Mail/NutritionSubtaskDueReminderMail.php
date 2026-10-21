<?php

namespace App\Mail;

use App\Models\NutritionSubtask;
use App\Models\NutritionTeamUser;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NutritionSubtaskDueReminderMail extends Mailable
{
    use Queueable, SerializesModels;
    public function __construct(public NutritionSubtask $subtask, public NutritionTeamUser $recipient) {}
    public function envelope(): Envelope { return new Envelope(subject: 'Subtask due soon: '.$this->subtask->title); }
    public function content(): Content { return new Content(view: 'mail.nutrition-subtask-due-reminder'); }
}
