<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ticket extends Model
{
    protected $fillable = [
        'ticket_number',
        'system_id',
        'module_id',
        'full_name',
        'phone',
        'email',
        'region_id',
        'facility_id',
        'department_id',
        'issue_type_id',
        'priority_level_id',
        'description',
        'attachment_path',
        'source_url',
        'browser_info',
        'device_info',
        'ip_address',
        'status_id',
        'assigned_to',
        'assigned_at',
        'last_worked_at',
        'last_reminder_sent_at',
        'resolution_category_id',
        'closure_reason_id',
        'expected_resolution_date',
        'resolution_summary',
        'training_recommended',
        'resolved_at',
        'closed_at',
    ];

    protected $casts = [
        'training_recommended' => 'boolean',
        'expected_resolution_date' => 'date',
        'assigned_at' => 'datetime',
        'last_worked_at' => 'datetime',
        'last_reminder_sent_at' => 'datetime',
        'resolved_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function system(): BelongsTo
    {
        return $this->belongsTo(SupportSystem::class, 'system_id');
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(SupportModule::class, 'module_id');
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function issueType(): BelongsTo
    {
        return $this->belongsTo(IssueType::class);
    }

    public function priorityLevel(): BelongsTo
    {
        return $this->belongsTo(PriorityLevel::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(TicketStatus::class, 'status_id');
    }

    public function assignedStaff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function resolutionCategory(): BelongsTo
    {
        return $this->belongsTo(ResolutionCategory::class);
    }

    public function closureReason(): BelongsTo
    {
        return $this->belongsTo(ClosureReason::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TicketComment::class);
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(TicketStatusLog::class);
    }
}
