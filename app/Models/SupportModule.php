<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportModule extends Model
{
    protected $fillable = ['system_id', 'name', 'code', 'description', 'active', 'sort_order', 'created_by', 'updated_by'];

    protected $casts = ['active' => 'boolean'];

    public function system(): BelongsTo
    {
        return $this->belongsTo(SupportSystem::class, 'system_id');
    }
}
