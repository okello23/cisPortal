<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlisRemoteBackupKeyHistory extends Model
{
    protected $table = 'alis_remote_backup_key_history';

    protected $fillable = [
        'facility_id',
        'action',
        'old_public_key',
        'new_public_key',
        'old_fingerprint',
        'new_fingerprint',
        'reason_id',
        'comments',
        'performed_by',
        'performed_at',
        'deployment_batch_id',
    ];

    protected $casts = [
        'performed_at' => 'datetime',
    ];

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function reason(): BelongsTo
    {
        return $this->belongsTo(AlisRemoteBackupKeyUpdateReason::class, 'reason_id');
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function deployment(): BelongsTo
    {
        return $this->belongsTo(AlisRemoteBackupDeployment::class, 'deployment_batch_id');
    }
}
