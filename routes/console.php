<?php

use App\Support\TicketReminderService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('tickets:send-reminders', function (TicketReminderService $ticketReminderService) {
    $sent = $ticketReminderService->sendStaleAssignmentReminders();

    $this->info("Sent {$sent} stale ticket reminder(s).");
})->purpose('Send reminders for tickets that have not been worked on within 24 hours');

Schedule::command('tickets:send-reminders')->hourly();
