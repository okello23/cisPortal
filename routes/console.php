<?php

use App\Support\TicketReminderService;
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
