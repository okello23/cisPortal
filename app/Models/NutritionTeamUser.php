<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class NutritionTeamUser extends Model
{
    protected $fillable = ['user_id', 'designation', 'place_of_work'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(NutritionTask::class, 'assigned_to');
    }

    public function assignedTasks(): BelongsToMany
    {
        return $this->belongsToMany(NutritionTask::class, 'nutrition_task_assignees')->withTimestamps();
    }

    public function subtasks(): BelongsToMany
    {
        return $this->belongsToMany(NutritionSubtask::class, 'nutrition_subtask_assignees')->withTimestamps();
    }
}
