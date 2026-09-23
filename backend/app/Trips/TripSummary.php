<?php

namespace App\Trips;

use App\Models\Trip;
use App\Models\TripStop;

/** The end-of-trip sheet: what happened at each stop and whether the cash adds up. */
class TripSummary
{
    public static function of(Trip $trip): array
    {
        $trip->loadMissing('stops.shop');
        $counts = array_fill_keys(TripStop::OUTCOMES, 0);
        $pending = 0;
        foreach ($trip->stops as $stop) {
            $stop->outcome === null ? $pending++ : $counts[$stop->outcome]++;
        }
        $delivered = $trip->stops->where('outcome', TripStop::DELIVERED);

        return [
            'counts' => $counts,
            'pending' => $pending,
            'stops' => $trip->stops->count(),
            'cash' => [
                'dueCents' => (int) $trip->stops->sum('amount_due_cents'),
                'expectedCents' => $trip->cash_expected_cents,
                'collectedCents' => $trip->cash_collected_cents,
                'differenceCents' => $trip->cash_collected_cents - $trip->cash_expected_cents,
            ],
            'closed' => $trip->stops->where('outcome', TripStop::CLOSED)->map(fn (TripStop $s) => [
                'stopId' => $s->id,
                'shopId' => $s->shop_id,
                'name' => $s->shop->name,
                'at' => $s->outcome_at?->toIso8601String(),
            ])->values(),
            'shortfalls' => $delivered
                ->filter(fn (TripStop $s) => $s->amount_due_cents !== null && ($s->amount_collected_cents ?? 0) < $s->amount_due_cents)
                ->map(fn (TripStop $s) => [
                    'stopId' => $s->id,
                    'name' => $s->shop->name,
                    'dueCents' => $s->amount_due_cents,
                    'collectedCents' => $s->amount_collected_cents ?? 0,
                ])->values(),
        ];
    }
}
