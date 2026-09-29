<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NutritionTask extends Model
{
    public const TYPES = [
        'visualisation' => 'Visualisation',
        'dataset' => 'Dataset',
        'analysis' => 'Analysis',
        'other' => 'Other Task',
    ];

    public const PRIORITIES = ['low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'urgent' => 'Urgent'];

    public const STATUSES = [
        'not_started' => 'Not Started',
        'started' => 'Started',
        'in_progress' => 'In Progress',
        'completed' => 'Completed',
    ];

    protected $fillable = [
        'task_number', 'entry_date', 'requestor_designation', 'requestor_place_of_work',
        'requestor_name', 'requestor_email', 'requestor_phone', 'request_type',
        'other_request_type', 'description', 'requested_assignee_email',
        'requested_assignee_name', 'priority', 'status', 'assigned_to', 'assigned_by',
        'due_date', 'assigned_at', 'completed_at', 'last_reminder_sent_at', 'progress_notes',
    ];

    protected $casts = [
        'entry_date' => 'date',
        'due_date' => 'date',
        'assigned_at' => 'datetime',
        'completed_at' => 'datetime',
        'last_reminder_sent_at' => 'datetime',
    ];

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(NutritionTeamUser::class, 'assigned_to');
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(NutritionTaskHistory::class)->latest();
    }

    public function requestTypeLabel(): string
    {
        return $this->request_type === 'other'
            ? ($this->other_request_type ?: self::TYPES['other'])
            : (self::TYPES[$this->request_type] ?? ucfirst($this->request_type));
    }
}
