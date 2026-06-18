<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_ICT_ADMIN = 'ict_admin';
    public const ROLE_ICT_MANAGER = 'ict_manager';
    public const ROLE_ICT_SUPERVISOR = 'ict_supervisor';
    public const ROLE_ICT_SUPPORT_STAFF = 'ict_support_staff';
    public const ROLE_DEVELOPER = 'developer';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'role',
        'active',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function assignedTickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'assigned_to');
    }

    public function hasAnyRole(array $roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public static function roleLabels(): array
    {
        return [
            self::ROLE_ICT_ADMIN => 'ICT Admin',
            self::ROLE_ICT_MANAGER => 'ICT Manager',
            self::ROLE_ICT_SUPERVISOR => 'Software Development Supervisor',
            self::ROLE_ICT_SUPPORT_STAFF => 'ICT Support Staff',
            self::ROLE_DEVELOPER => 'Developer',
        ];
    }
}
