<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AlisRemoteBackupDeployment extends Model
{
    protected $fillable = [
        'deployment_batch_reference',
        'deployed_by',
        'deployed_at',
        'status',
        'total_active_keys',
        'total_pending_changes',
        'backup_server',
        'authorized_keys_path',
        'error_message',
    ];

    protected $casts = [
        'deployed_at' => 'datetime',
    ];

    public function deployer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deployed_by');
    }

    public function history(): HasMany
    {
        return $this->hasMany(AlisRemoteBackupKeyHistory::class, 'deployment_batch_id');
    }
}
