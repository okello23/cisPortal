<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

class NutritionSubtask extends Model
{
    protected $fillable = ['nutrition_task_id', 'title', 'description', 'status', 'due_date', 'created_by', 'completed_at', 'last_reminder_sent_at', 'theme_id'];

    protected $casts = ['due_date' => 'date', 'completed_at' => 'datetime', 'last_reminder_sent_at' => 'datetime'];

    public function task(): BelongsTo
    {
        return $this->belongsTo(NutritionTask::class, 'nutrition_task_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function theme(): BelongsTo
    {
        return $this->belongsTo(NutritionTheme::class, 'theme_id');
    }

    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(NutritionTeamUser::class, 'nutrition_subtask_assignees')->withTimestamps();
    }

    public function effectiveAssignees(): Collection
    {
        return $this->assignees->isNotEmpty() ? $this->assignees : $this->task->assignees;
    }
}
