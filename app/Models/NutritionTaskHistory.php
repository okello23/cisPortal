<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NutritionTaskHistory extends Model
{
    protected $fillable = [
        'nutrition_task_id', 'changed_by', 'old_status', 'new_status',
        'old_assignee_id', 'new_assignee_id', 'note',
    ];

    public function task(): BelongsTo { return $this->belongsTo(NutritionTask::class, 'nutrition_task_id'); }
    public function changedBy(): BelongsTo { return $this->belongsTo(User::class, 'changed_by'); }
    public function oldAssignee(): BelongsTo { return $this->belongsTo(NutritionTeamUser::class, 'old_assignee_id'); }
    public function newAssignee(): BelongsTo { return $this->belongsTo(NutritionTeamUser::class, 'new_assignee_id'); }
}
