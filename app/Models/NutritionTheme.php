<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NutritionTheme extends Model
{
    protected $fillable = ['code', 'name', 'sort_order'];

    public function tasks(): HasMany
    {
        return $this->hasMany(NutritionTask::class, 'theme_id');
    }

    public function subtasks(): HasMany
    {
        return $this->hasMany(NutritionSubtask::class, 'theme_id');
    }
}
