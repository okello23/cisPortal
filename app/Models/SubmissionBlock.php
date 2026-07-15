<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubmissionBlock extends Model
{
    protected $fillable = [
        'block_type',
        'block_value',
        'reason',
        'blocked_at',
        'expires_at',
        'blocked_by',
        'is_active',
    ];

    protected $casts = [
        'blocked_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];
}
