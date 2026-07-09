<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlisBackupConfiguration extends Model
{
    public const STATUS_PENDING_PROVISIONING = 'pending_provisioning';
    public const STATUS_PROVISIONED = 'provisioned';
    public const STATUS_PROVISIONING_FAILED = 'provisioning_failed';
    public const STATUS_DISABLED = 'disabled';

    protected $fillable = [
        'facility_id',
        'backup_directory_name',
        'database_name',
        'database_username',
        'database_password',
        'status',
        'created_by',
        'updated_by',
        'provisioned_at',
        'last_provisioning_error',
    ];

    protected $hidden = [
        'database_password',
    ];

    protected function casts(): array
    {
        return [
            'database_password' => 'encrypted',
            'provisioned_at' => 'datetime',
        ];
    }

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
}
