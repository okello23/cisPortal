<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements CanResetPasswordContract
{
    /** @use HasFactory<UserFactory> */
    use CanResetPassword, HasFactory, Notifiable;

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
        'force_password_change',
        'password_changed_at',
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
            'force_password_change' => 'boolean',
            'password_changed_at' => 'datetime',
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

    public function requiresPasswordChange(): bool
    {
        if ($this->force_password_change || $this->password_changed_at === null) {
            return true;
        }

        return $this->password_changed_at->lt(now()->subDays(90));
    }

    public function passwordExpired(): bool
    {
        return $this->password_changed_at !== null
            && $this->password_changed_at->lt(now()->subDays(90));
    }
}
