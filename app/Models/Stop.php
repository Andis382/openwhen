<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Stop extends Model
{
    protected $fillable = ['shop_id', 'position', 'suggested_hour', 'reason'];

    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function suggestedTime(): ?string
    {
        return $this->suggested_hour === null
            ? null
            : sprintf('%02d:00', $this->suggested_hour);
    }

    /** Why this stop is where it is. A driver who cannot see that stops trusting the list. */
    public function reasonLabel(): string
    {
        return __('plan.reason.'.($this->reason ?: 'unknown'));
    }
}
