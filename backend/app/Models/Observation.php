<?php

namespace App\Models;

use App\Hours\Sighting;
use App\Hours\Slots;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** "Shop 37 was shut at 08:40 on a Monday": the raw material of the opening-hours model. */
class Observation extends Model
{
    use BelongsToOrganization;

    public const FROM_VISIT = 'VISIT';

    public const FROM_IMPORT = 'IMPORT';

    protected $fillable = [
        'organization_id', 'shop_id', 'trip_stop_id', 'observed_at', 'weekday', 'minute_of_day',
        'is_open', 'outcome', 'source', 'client_uuid',
    ];

    protected function casts(): array
    {
        return [
            'observed_at' => 'datetime',
            'weekday' => 'integer',
            'minute_of_day' => 'integer',
            'is_open' => 'boolean',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function tripStop(): BelongsTo
    {
        return $this->belongsTo(TripStop::class);
    }

    public function sighting(): Sighting
    {
        return new Sighting($this->weekday, $this->minute_of_day, $this->is_open);
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'observedAt' => $this->observed_at->toIso8601String(),
            'weekday' => $this->weekday,
            'time' => Slots::format($this->minute_of_day),
            'open' => $this->is_open,
            'outcome' => $this->outcome,
            'source' => $this->source,
        ];
    }
}
