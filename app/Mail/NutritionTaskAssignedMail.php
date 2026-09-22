<?php

namespace App\Mail;

use App\Models\NutritionTask;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NutritionTaskAssignedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public NutritionTask $task) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Nutrition task assigned: '.$this->task->task_number);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.nutrition-task-assigned');
    }
}
