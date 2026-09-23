<?php

namespace App\Models;

use App\Hours\Slots;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A standing round: which shops, in which order, on which weekdays, usually by whom. */
class RouteTemplate extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['organization_id', 'name', 'weekdays', 'default_driver_id', 'start_minute', 'active'];

    protected function casts(): array
    {
        return [
            'weekdays' => 'array',
            'start_minute' => 'integer',
            'active' => 'boolean',
        ];
    }

    public function stops(): HasMany
    {
        return $this->hasMany(RouteTemplateStop::class)->orderBy('position');
    }

    public function defaultDriver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'default_driver_id');
    }

    public function runsOn(int $weekday): bool
    {
        return in_array($weekday, $this->weekdays ?? [], true);
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'weekdays' => $this->weekdays,
            'startTime' => Slots::format($this->start_minute),
            'active' => $this->active,
            'defaultDriver' => $this->defaultDriver ? ['id' => $this->defaultDriver->id, 'name' => $this->defaultDriver->name] : null,
        ];
    }
}
