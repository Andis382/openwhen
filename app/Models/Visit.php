<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One stop, and what the driver found.
 *
 * Four outcomes, and the distinction that matters is not "did we sell
 * something" but "was the shutter up". A refusal and a delivery are both
 * evidence the shop was open; only a closed shutter is evidence it was not.
 * An absent owner is the awkward one: the shop was open, the visit was still
 * wasted, and conflating those two facts is how every field-sales app loses
 * the only signal worth having.
 */
class Visit extends Model
{
    public const DELIVERED = 'delivered';
    public const REFUSED = 'refused';
    public const OWNER_ABSENT = 'owner_absent';
    public const CLOSED = 'closed';

    public const OUTCOMES = [self::DELIVERED, self::CLOSED, self::OWNER_ABSENT, self::REFUSED];

    protected $fillable = [
        'user_id', 'route_id', 'outcome', 'observed_at', 'weekday', 'hour',
        'lat', 'lng', 'note', 'client_uuid',
    ];

    protected function casts(): array
    {
        return ['observed_at' => 'datetime', 'lat' => 'float', 'lng' => 'float'];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class);
    }

    /** Was the shutter up? The only question the estimator asks. */
    public function foundOpen(): bool
    {
        return $this->outcome !== self::CLOSED;
    }

    /** Did the van stop for nothing? A different question, tracked separately. */
    public function wasWasted(): bool
    {
        return in_array($this->outcome, [self::CLOSED, self::OWNER_ABSENT], true);
    }

    public function outcomeLabel(): string
    {
        return __('visit.outcome.'.$this->outcome);
    }

    public function icon(): string
    {
        return match ($this->outcome) {
            self::DELIVERED => 'delivered',
            self::CLOSED => 'closed',
            self::OWNER_ABSENT => 'absent',
            default => 'refused',
        };
    }

    public function tone(): string
    {
        return match ($this->outcome) {
            self::DELIVERED => 'ok',
            self::CLOSED => 'bad',
            self::OWNER_ABSENT => 'warn',
            default => 'quiet',
        };
    }
}
