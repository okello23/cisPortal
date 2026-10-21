<?php

namespace App\Support;

use App\Mail\NutritionSubtaskDueReminderMail;
use App\Mail\NutritionTaskDueReminderMail;
use App\Models\NutritionSubtask;
use App\Models\NutritionTask;

class NutritionTaskReminderService
{
    public function __construct(private readonly OutboundMail $outboundMail) {}

    public function sendDueReminders(): int
    {
        $sent = 0;
        $tasks = NutritionTask::query()->with('assignees.user')->whereHas('assignees')->whereNotNull('due_date')
            ->where('status', '!=', 'completed')->whereDate('due_date', '<=', today()->addDay())
            ->where(fn ($q) => $q->whereNull('last_reminder_sent_at')->orWhereDate('last_reminder_sent_at', '<', today()))->get();
        foreach ($tasks as $task) {
            $allSent = true;
            foreach ($task->assignees as $person) {
                $ok = $person->user?->email && $this->outboundMail->send($person->user->email, new NutritionTaskDueReminderMail($task, $person));
                $allSent = $ok && $allSent; $sent += $ok ? 1 : 0;
            }
            if ($allSent && $task->assignees->isNotEmpty()) $task->update(['last_reminder_sent_at' => now()]);
        }

        $subtasks = NutritionSubtask::query()->with(['task.assignees.user', 'assignees.user'])->whereNotNull('due_date')
            ->where('status', '!=', 'completed')->whereDate('due_date', '<=', today()->addDay())
            ->where(fn ($q) => $q->whereNull('last_reminder_sent_at')->orWhereDate('last_reminder_sent_at', '<', today()))->get();
        foreach ($subtasks as $subtask) {
            $people = $subtask->effectiveAssignees()->unique('id'); $allSent = true;
            foreach ($people as $person) {
                $ok = $person->user?->email && $this->outboundMail->send($person->user->email, new NutritionSubtaskDueReminderMail($subtask, $person));
                $allSent = $ok && $allSent; $sent += $ok ? 1 : 0;
            }
            if ($allSent && $people->isNotEmpty()) $subtask->update(['last_reminder_sent_at' => now()]);
        }
        return $sent;
    }
}
