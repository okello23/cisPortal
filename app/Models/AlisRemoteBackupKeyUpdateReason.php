<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AlisRemoteBackupKeyUpdateReason extends Model
{
    protected $fillable = [
        'name',
        'description',
        'active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function isOtherReason(): bool
    {
        return mb_strtolower(trim($this->name)) === 'other';
    }
}
