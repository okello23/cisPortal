<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    public const GLOBAL = 'global';

    public const MICRONUTRIENT = 'micronutrient';

    protected $fillable = ['name', 'code', 'category', 'access_role'];

    public static function categories(): array
    {
        return [self::GLOBAL => 'Global', self::MICRONUTRIENT => 'Micronutrient'];
    }

    public static function accessRoles(string $category): array
    {
        return array_filter(User::roleLabels(), fn ($code) => in_array($code, [User::ROLE_TASK_ADMIN, User::ROLE_NUTRITION_USER], true)
                === ($category === self::MICRONUTRIENT), ARRAY_FILTER_USE_KEY);
    }

    public static function choices(string $category): array
    {
        return self::query()->where('category', $category)->orderBy('name')->pluck('name', 'code')->all();
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'role_user');
    }

    public function isBuiltIn(): bool
    {
        return array_key_exists($this->code, User::roleLabels());
    }
}
