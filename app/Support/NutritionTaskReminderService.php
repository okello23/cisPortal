<?php

namespace App\Support;

use App\Mail\NutritionTaskDueReminderMail;
use App\Models\NutritionTask;

class NutritionTaskReminderService
{
    public function __construct(private readonly OutboundMail $outboundMail) {}

    public function sendDueReminders(): int
    {
        $tasks = NutritionTask::query()
            ->with('assignee.user')
            ->whereNotNull('assigned_to')
            ->whereNotNull('due_date')
            ->where('status', '!=', 'completed')
            ->whereDate('due_date', '<=', today()->addDay())
            ->where(fn ($query) => $query->whereNull('last_reminder_sent_at')->orWhereDate('last_reminder_sent_at', '<', today()))
            ->get();

        $sent = 0;
        foreach ($tasks as $task) {
            if (! $task->assignee?->user?->email) {
                continue;
            }
            if ($this->outboundMail->send($task->assignee->user->email, new NutritionTaskDueReminderMail($task))) {
                $task->update(['last_reminder_sent_at' => now()]);
                $sent++;
            }
        }

        return $sent;
    }
}
