<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const DISPATCHER = 'dispatcher';
    public const DRIVER = 'driver';

    protected $fillable = ['company_id', 'name', 'email', 'password', 'role', 'phone', 'locale', 'timezone'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['email_verified_at' => 'datetime', 'password' => 'hashed'];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function routes(): HasMany
    {
        return $this->hasMany(Route::class);
    }

    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class);
    }

    public function isDispatcher(): bool
    {
        return $this->role === self::DISPATCHER;
    }

    public function shortName(): string
    {
        return explode(' ', trim($this->name))[0] ?: $this->name;
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->name)) ?: [];
        $letters = array_map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)), array_slice($parts, 0, 2));

        return implode('', $letters) ?: '?';
    }

    public function roleLabel(): string
    {
        return __('ui.role.'.$this->role);
    }
}
