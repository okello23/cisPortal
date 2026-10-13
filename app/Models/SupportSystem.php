<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SupportSystem extends Model
{
    protected $fillable = ['name', 'code', 'description', 'active', 'sort_order', 'created_by', 'updated_by'];

    protected $casts = ['active' => 'boolean'];

    public function modules(): HasMany
    {
        return $this->hasMany(SupportModule::class, 'system_id');
    }

    public function inHouseBackupConfiguration(): HasOne
    {
        return $this->hasOne(InHouseBackupConfiguration::class, 'system_id');
    }
}
