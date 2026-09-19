<?php

namespace App\Services;

use App\Models\Route;
use App\Models\Stop;
use App\Models\Visit;
use Illuminate\Support\Facades\DB;

/**
 * Put tomorrow's stops in the order that finds the most shutters up.
 *
 * Not a travelling-salesman solver, and deliberately not one. A distributor
 * with five vans already knows the geography of his own town far better than
 * any optimiser; what nobody knows is that number 37 is never open before ten
 * on a Monday. This reorders for time, not distance, and leaves everything it
 * has no evidence about exactly where the driver put it.
 *
 * That last part matters more than the sorting. A tool that shuffles a
 * driver's whole list on its first day, on two weeks of data, gets switched
 * off on its second.
 */
class RouteSequencer
{
    public function __construct(private readonly OpenWindowEstimator $estimator)
    {
    }

    /**
     * @return array<int, array{stop: Stop, hour: ?int, reason: string, moved: bool}>
     */
    public function plan(Route $route, bool $persist = true): array
    {
        $stops = $route->stops()->with('shop')->orderBy('position')->get();

        if ($stops->isEmpty()) {
            return [];
        }

        // One query for every shop's history on this weekday, rather than one
        // per stop. A route is thirty to eighty shops.
        $history = Visit::query()
            ->whereIn('shop_id', $stops->pluck('shop_id'))
            ->where('weekday', $route->weekday)
            ->get()
            ->groupBy('shop_id');

        $rows = [];
        foreach ($stops as $index => $stop) {
            $hour = $this->estimator->bestHour(
                $stop->shop,
                $route->weekday,
                $history->get($stop->shop_id, collect()),
            );

            $rows[] = [
                'stop' => $stop,
                'index' => $index,
                'hour' => $hour,
                'reason' => $hour === null ? 'unknown' : 'window',
            ];
        }

        // Shops with no profile keep their relative order and sit at the median
        // of the day, so an unknown shop drifts towards the middle instead of
        // being shoved to the end of a list it may well belong at the front of.
        $known = array_values(array_filter($rows, fn (array $row) => $row['hour'] !== null));
        $median = $known === []
            ? 12.0
            : (float) collect($known)->median('hour');

        usort($rows, function (array $a, array $b) use ($median) {
            $keyA = $a['hour'] ?? $median;
            $keyB = $b['hour'] ?? $median;

            return $keyA <=> $keyB ?: $a['index'] <=> $b['index'];
        });

        $plan = [];
        foreach ($rows as $position => $row) {
            $moved = $position !== $row['index'];

            $plan[] = [
                'stop' => $row['stop'],
                'hour' => $row['hour'],
                'reason' => $row['hour'] === null ? 'unknown' : ($moved ? 'window' : 'unchanged'),
                'moved' => $moved,
            ];
        }

        if ($persist) {
            DB::transaction(function () use ($plan, $route) {
                foreach ($plan as $position => $row) {
                    $row['stop']->forceFill([
                        'position' => $position,
                        'suggested_hour' => $row['hour'],
                        'reason' => $row['reason'],
                    ])->save();
                }

                $route->forceFill(['sequenced_at' => now()])->save();
            });
        }

        return $plan;
    }

    /**
     * Copy a route to another date, keeping the shops and dropping everything
     * that was true only of that day.
     *
     * This is how the product is actually used: the same van does the same
     * street every Tuesday, and the only thing that should change week to week
     * is the order.
     */
    public function repeat(Route $route, \Carbon\CarbonInterface $onDate, ?int $driverId = null): Route
    {
        $copy = $route->company->routes()->create([
            'user_id' => $driverId ?? $route->user_id,
            'name' => $route->name,
            'on_date' => $onDate->toDateString(),
            'weekday' => (int) $onDate->isoWeekday(),
        ]);

        foreach ($route->stops()->orderBy('position')->get() as $position => $stop) {
            $copy->stops()->create(['shop_id' => $stop->shop_id, 'position' => $position]);
        }

        $this->plan($copy);

        return $copy->fresh(['stops.shop']);
    }
}
