<?php

namespace App\Trips;

use App\Hours\HoursRule;
use App\Hours\OpenState;
use App\Hours\ShopHours;
use App\Hours\Slots;
use App\Models\Trip;
use App\Models\TripStop;
use App\Routing\GeoPoint;
use App\Routing\RouteOptimizer;
use App\Routing\RoutePlan;
use App\Routing\RouteStop;
use Illuminate\Support\Facades\DB;

/**
 * The dispatcher's side of a trip: arrival times and odds for the current order, the optimised
 * alternative, and saving an order back onto the stops.
 */
class TripPlanner
{
    public function __construct(private readonly ShopHours $hours) {}

    /** The trip as it stands: every stop with ETA, P(open), a warning and today's rules. */
    public function current(Trip $trip): array
    {
        $trip->loadMissing('stops.shop', 'driver');
        $plan = $this->optimizer($trip)->evaluate($trip->stops->pluck('id')->all());

        return $this->describe($trip, $plan);
    }

    /** Before and after, without saving anything. */
    public function optimise(Trip $trip): array
    {
        $trip->loadMissing('stops.shop', 'driver');
        $optimizer = $this->optimizer($trip);
        $current = $trip->stops->pluck('id')->all();

        return [
            'before' => $this->describe($trip, $optimizer->evaluate($current)),
            'after' => $this->describe($trip, $optimizer->optimise($current)),
        ];
    }

    /** @param list<int> $stopIds the new driving order; must be exactly the trip's stops */
    public function reorder(Trip $trip, array $stopIds, bool $optimised = false): array
    {
        $trip->loadMissing('stops.shop');
        $plan = $this->optimizer($trip)->evaluate($stopIds);
        $this->save($trip, $plan);
        if ($optimised) {
            $trip->forceFill(['optimised_at' => now()])->save();
        }

        return $this->current($trip->fresh());
    }

    /** Recomputes arrival times for the order the trip already has (new trip, new start time). */
    public function refreshEtas(Trip $trip): void
    {
        $trip->load('stops.shop');
        $this->save($trip, $this->optimizer($trip)->evaluate($trip->stops->pluck('id')->all()));
    }

    /** Where the van starts: the depot, or the middle of the trip's shops when none is set. */
    public function depot(Trip $trip): GeoPoint
    {
        $depot = $trip->organization->depot();
        if ($depot !== null) {
            return $depot;
        }
        $shops = $trip->stops->pluck('shop');

        return $shops->isEmpty() ? new GeoPoint(0, 0) : new GeoPoint($shops->avg('lat'), $shops->avg('lng'));
    }

    private function optimizer(Trip $trip): RouteOptimizer
    {
        $models = $this->hours->models($trip->stops->pluck('shop'));
        $weekday = $trip->weekday();

        return new RouteOptimizer(
            $this->depot($trip),
            $trip->start_minute,
            $trip->stops->map(fn (TripStop $s) => RouteStop::fromModel($s->id, $s->shop->point(), $models[$s->shop_id], $weekday))->all(),
        );
    }

    private function save(Trip $trip, RoutePlan $plan): void
    {
        DB::transaction(function () use ($trip, $plan) {
            foreach ($plan->visits as $i => $visit) {
                TripStop::whereKey($visit['id'])->where('trip_id', $trip->id)->update([
                    'position' => $i + 1,
                    'planned_eta_minute' => $visit['eta'],
                    'planned_p_open' => $visit['pOpen'],
                ]);
            }
        });
        $trip->unsetRelation('stops');
    }

    private function describe(Trip $trip, RoutePlan $plan): array
    {
        $weekday = $trip->weekday();
        $stops = $trip->stops->keyBy('id');
        $models = $this->hours->models($stops->pluck('shop'));

        return [
            'summary' => $plan->summary() + [
                'finishTime' => Slots::format($plan->finishMinute % 1440),
                'stops' => count($plan->visits),
                'expectedOpen' => round(count($plan->visits) - $plan->expectedClosed, 2),
            ],
            'stops' => array_map(function (array $visit) use ($stops, $models, $weekday) {
                /** @var TripStop $stop */
                $stop = $stops[$visit['id']];
                $shop = $stop->shop;
                $model = $models[$shop->id];

                return [
                    'id' => $stop->id,
                    'eta' => Slots::format($visit['eta'] % 1440),
                    'pOpen' => $visit['pOpen'],
                    'kmFromPrevious' => $visit['kmFromPrevious'],
                    'warning' => OpenState::arrivalWarning($model, $weekday, $visit['eta']),
                    'rules' => array_values(array_map(
                        fn (HoursRule $r) => $r->toArray(),
                        array_filter($this->hours->rules($shop), fn (HoursRule $r) => $r->appliesTo($weekday)),
                    )),
                    'amountDueCents' => $stop->amount_due_cents,
                    'outcome' => $stop->outcome,
                    'shop' => [
                        'id' => $shop->id,
                        'name' => $shop->name,
                        'address' => $shop->address,
                        'town' => $shop->town,
                        'lat' => $shop->lat,
                        'lng' => $shop->lng,
                    ],
                ];
            }, $plan->visits),
        ];
    }
}
