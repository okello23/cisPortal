<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Facility extends Model
{
    protected $fillable = [
        'region_id',
        'name',
        'code',
        'source_system',
        'external_id',
        'facility_type',
        'moh_id',
        'nhlds_uuid',
        'district_name',
        'subcounty_name',
        'phone',
        'email',
        'source_payload',
        'description',
        'active',
        'sort_order',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'active' => 'boolean',
        'source_payload' => 'array',
    ];

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function alisBackupConfiguration(): HasOne
    {
        return $this->hasOne(AlisBackupConfiguration::class);
    }
}
