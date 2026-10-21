<?php

namespace App\Mail;

use App\Models\NutritionSubtask;
use App\Models\NutritionTeamUser;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NutritionSubtaskAssignedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public NutritionSubtask $subtask, public NutritionTeamUser $recipient) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Nutrition subtask assigned: '.$this->subtask->task->task_number.' - '.$this->subtask->title);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.nutrition-subtask-assigned');
    }
}
