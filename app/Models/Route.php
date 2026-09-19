<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Route extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'name', 'on_date', 'weekday', 'sequenced_at'];

    protected function casts(): array
    {
        return ['on_date' => 'date', 'sequenced_at' => 'datetime'];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function stops(): HasMany
    {
        return $this->hasMany(Stop::class)->orderBy('position');
    }

    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class);
    }

    public function title(): string
    {
        return $this->name ?: __('ui.route.untitled');
    }
}
