<?php

namespace App\Models;

use App\Hours\Slots;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One van running one route on one date. */
class Trip extends Model
{
    use BelongsToOrganization;

    public const PLANNED = 'PLANNED';

    public const IN_PROGRESS = 'IN_PROGRESS';

    public const DONE = 'DONE';

    protected $fillable = [
        'organization_id', 'route_template_id', 'name', 'date', 'driver_id', 'status', 'start_minute',
        'started_at', 'finished_at', 'published_at', 'optimised_at', 'cash_expected_cents', 'cash_collected_cents',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'start_minute' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'published_at' => 'datetime',
            'optimised_at' => 'datetime',
            'cash_expected_cents' => 'integer',
            'cash_collected_cents' => 'integer',
        ];
    }

    public function stops(): HasMany
    {
        return $this->hasMany(TripStop::class)->orderBy('position');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function routeTemplate(): BelongsTo
    {
        return $this->belongsTo(RouteTemplate::class);
    }

    public function isPlanned(): bool
    {
        return $this->status === self::PLANNED;
    }

    public function weekday(): int
    {
        return $this->date->dayOfWeekIso;
    }

    /** The header every trip screen shows. */
    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'date' => $this->date->toDateString(),
            'weekday' => $this->weekday(),
            'status' => $this->status,
            'startTime' => Slots::format($this->start_minute),
            'startedAt' => $this->started_at?->toIso8601String(),
            'finishedAt' => $this->finished_at?->toIso8601String(),
            'publishedAt' => $this->published_at?->toIso8601String(),
            'optimisedAt' => $this->optimised_at?->toIso8601String(),
            'driver' => $this->driver ? ['id' => $this->driver->id, 'name' => $this->driver->name] : null,
            'routeId' => $this->route_template_id,
            'cashExpectedCents' => $this->cash_expected_cents,
            'cashCollectedCents' => $this->cash_collected_cents,
        ];
    }
}
