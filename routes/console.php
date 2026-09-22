<?php

use App\Support\TicketReminderService;
use App\Support\NutritionTaskReminderService;
use App\Support\OutboundMail;
use App\Mail\EmailConnectivityTestMail;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('tickets:send-reminders', function (TicketReminderService $ticketReminderService) {
    $sent = $ticketReminderService->sendScheduledReminders();

    $this->info("Sent {$sent['unresolved']} unresolved ticket reminder(s) and {$sent['feedback']} rating reminder(s).");
})->purpose('Send 3-day unresolved ticket and feedback reminder emails');

Schedule::command('tickets:send-reminders')->hourly();

Artisan::command('nutrition-tasks:send-reminders', function (NutritionTaskReminderService $service) {
    $this->info('Sent '.$service->sendDueReminders().' Nutrition task reminder(s).');
})->purpose('Send reminders for incomplete Nutrition tasks approaching their due date');

Schedule::command('nutrition-tasks:send-reminders')->dailyAt('08:00')->timezone('Africa/Kampala');

Artisan::command('mail:send-test {recipient}', function (OutboundMail $mail) {
    $recipient = (string) $this->argument('recipient');

    if (filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
        $this->error('Enter a valid recipient email address.');
        return 1;
    }

    if (! $mail->send($recipient, new EmailConnectivityTestMail())) {
        $this->error('Email was not accepted. Check your mail settings and storage/logs/laravel.log.');
        return 1;
    }

    $this->info('The mail server accepted the test message. Check the recipient inbox and Spam folder.');
    return 0;
})->purpose('Send a test email to verify outgoing mail configuration');
