<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AlisRemoteBackupKey extends Model
{
    protected $fillable = [
        'facility_id',
        'public_key',
        'fingerprint',
        'key_type',
        'key_comment',
        'status',
        'deployment_status',
        'created_by',
        'updated_by',
        'deployed_by',
        'deployed_at',
        'last_deployment_error',
    ];

    protected $casts = [
        'deployed_at' => 'datetime',
    ];

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function deployer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deployed_by');
    }

    public function history(): HasMany
    {
        return $this->hasMany(AlisRemoteBackupKeyHistory::class, 'facility_id', 'facility_id');
    }
}
