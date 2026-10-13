<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InHouseBackupConfiguration extends Model
{
    public const DATABASE_TYPES = ['mysql', 'postgresql', 'mongodb'];

    protected $fillable = [
        'system_id', 'system_name', 'server_name', 'database_type', 'database_name', 'database_username',
        'database_password', 'backup_directory_name', 'status', 'provisioned_at', 'last_provisioning_error', 'created_by', 'updated_by',
    ];

    protected $hidden = ['database_password'];

    protected function casts(): array
    {
        return ['database_password' => 'encrypted', 'provisioned_at' => 'datetime'];
    }

    public function system(): BelongsTo
    {
        return $this->belongsTo(SupportSystem::class, 'system_id');
    }
}
