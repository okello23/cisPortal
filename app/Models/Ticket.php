<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class Ticket extends Model
{
    protected $fillable = [
        'ticket_number',
        'system_id',
        'module_id',
        'designation_id',
        'issue_started_at',
        'full_name',
        'phone',
        'email',
        'lab_manager_name',
        'lab_manager_email',
        'region_id',
        'district_name',
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
        'submission_uuid',
        'content_fingerprint',
        'submission_risk_score',
        'submission_risk_level',
        'submission_risk_reasons',
        'is_suspected_spam',
        'is_possible_duplicate',
        'quarantined_at',
        'quarantined_by',
        'quarantine_reason',
        'reviewed_at',
        'reviewed_by',
        'duplicate_of_ticket_id',
        'duplicate_confidence',
        'duplicate_review_status',
        'turnstile_verified',
        'turnstile_error_code',
        'submission_review_status',
        'released_to_queue_at',
        'status_id',
        'assigned_to',
        'assigned_at',
        'last_worked_at',
        'last_reminder_sent_at',
        'feedback_reminder_sent_at',
        'resolution_category_id',
        'closure_reason_id',
        'expected_resolution_date',
        'resolution_summary',
        'root_cause_analysis',
        'verification_testing',
        'data_loss_risk',
        'services_disrupted',
        'work_done',
        'recommendations',
        'challenges_faced',
        'training_recommended',
        'resolved_at',
        'closed_at',
    ];

    protected $casts = [
        'training_recommended' => 'boolean',
        'submission_risk_reasons' => 'array',
        'is_suspected_spam' => 'boolean',
        'is_possible_duplicate' => 'boolean',
        'turnstile_verified' => 'boolean',
        'issue_started_at' => 'date',
        'expected_resolution_date' => 'date',
        'quarantined_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'released_to_queue_at' => 'datetime',
        'assigned_at' => 'datetime',
        'last_worked_at' => 'datetime',
        'last_reminder_sent_at' => 'datetime',
        'feedback_reminder_sent_at' => 'datetime',
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

    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class);
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

    public function feedback(): HasOne
    {
        return $this->hasOne(TicketFeedback::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TicketAttachment::class);
    }

    public function duplicateMatches(): HasMany
    {
        return $this->hasMany(SubmissionDuplicateMatch::class);
    }

    public function duplicateParent(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'duplicate_of_ticket_id');
    }

    public function canReceiveFeedback(): bool
    {
        if ($this->email === null || $this->resolved_at === null || $this->status?->code !== 'resolved') {
            return false;
        }

        if ($this->relationLoaded('feedback')) {
            return $this->feedback === null;
        }

        return ! $this->feedback()->exists();
    }

    public function feedbackUrl(string $route = 'tickets.feedback.show'): string
    {
        return URL::signedRoute($route, ['ticket' => $this], absolute: false);
    }

    public function hasAttachment(): bool
    {
        if ($this->relationLoaded('attachments')) {
            return $this->attachments->isNotEmpty() || filled($this->attachment_path);
        }

        return $this->attachments()->exists() || filled($this->attachment_path);
    }

    public function attachmentUrl(): ?string
    {
        return $this->primaryAttachment()?->downloadUrl();
    }

    public function attachmentFilename(): ?string
    {
        return $this->primaryAttachment()?->original_filename
            ?? ($this->hasAttachment() ? basename((string) $this->attachment_path) : null);
    }

    public function attachmentExtension(): ?string
    {
        return $this->primaryAttachment()?->extension
            ?? ($this->hasAttachment() ? strtolower(pathinfo((string) $this->attachment_path, PATHINFO_EXTENSION)) : null);
    }

    public function hasImageAttachment(): bool
    {
        return in_array($this->attachmentExtension(), ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'], true);
    }

    public function hasPdfAttachment(): bool
    {
        return $this->attachmentExtension() === 'pdf';
    }

    public function primaryAttachment(): ?TicketAttachment
    {
        if ($this->relationLoaded('attachments')) {
            return $this->attachments->first();
        }

        return $this->attachments()->oldest()->first();
    }
}
