<?php

namespace App\Models;

use App\Hours\Slots;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A shop on a trip, and what happened when the van got there. */
class TripStop extends Model
{
    use BelongsToOrganization;

    public const DELIVERED = 'DELIVERED';

    public const CLOSED = 'CLOSED';

    public const OWNER_ABSENT = 'OWNER_ABSENT';

    public const REFUSED = 'REFUSED';

    public const SKIPPED = 'SKIPPED';

    public const OUTCOMES = [self::DELIVERED, self::CLOSED, self::OWNER_ABSENT, self::REFUSED, self::SKIPPED];

    /** Somebody was behind the counter: the shop was open, whatever else went wrong. */
    public const SHOP_OPEN = [self::DELIVERED, self::OWNER_ABSENT, self::REFUSED];

    protected $fillable = [
        'organization_id', 'trip_id', 'shop_id', 'position', 'planned_eta_minute', 'planned_p_open', 'amount_due_cents',
        'outcome', 'outcome_at', 'outcome_lat', 'outcome_lng', 'gps_accuracy_m', 'distance_m', 'note',
        'amount_collected_cents', 'proof_file_id', 'client_uuid', 'visits',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'planned_eta_minute' => 'integer',
            'planned_p_open' => 'float',
            'amount_due_cents' => 'integer',
            'outcome_at' => 'datetime',
            'outcome_lat' => 'float',
            'outcome_lng' => 'float',
            'gps_accuracy_m' => 'integer',
            'distance_m' => 'integer',
            'amount_collected_cents' => 'integer',
            'visits' => 'integer',
        ];
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function proofFile(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class, 'proof_file_id');
    }

    /** The outcome part, shared by the driver app, the plan and the visit history. */
    public function outcomeApi(): array
    {
        return [
            'outcome' => $this->outcome,
            'outcomeAt' => $this->outcome_at?->toIso8601String(),
            'amountDueCents' => $this->amount_due_cents,
            'amountCollectedCents' => $this->amount_collected_cents,
            'note' => $this->note,
            'gps' => $this->outcome_lat === null ? null : [
                'lat' => $this->outcome_lat,
                'lng' => $this->outcome_lng,
                'accuracyM' => $this->gps_accuracy_m,
                'distanceM' => $this->distance_m,
            ],
            'proofUrl' => $this->proof_file_id ? '/api/files/'.$this->proof_file_id : null,
            'visits' => $this->visits,
            'plannedEta' => $this->planned_eta_minute === null ? null : Slots::format($this->planned_eta_minute),
            'plannedPOpen' => $this->planned_p_open,
        ];
    }
}
