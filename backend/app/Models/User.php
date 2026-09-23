<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /** Runs the business: plans routes, sees everything, manages the team. */
    public const OWNER = 'OWNER';

    /** Plans and publishes routes like the owner, without team or company settings. */
    public const DISPATCHER = 'DISPATCHER';

    /** Drives: sees only the trips assigned to them. */
    public const DRIVER = 'DRIVER';

    /** Roles that plan routes and see every shop. */
    public const PLANNERS = [self::OWNER, self::DISPATCHER];

    /** Roles an owner may hand out with a join link. */
    public const INVITABLE_ROLES = [self::DRIVER, self::DISPATCHER];

    protected $fillable = ['organization_id', 'name', 'email', 'password', 'role', 'locale', 'last_login_at'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'locale' => $this->locale,
        ];
    }
}
