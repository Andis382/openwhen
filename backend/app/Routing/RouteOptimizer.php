<?php

namespace App\Routing;

use InvalidArgumentException;

/**
 * Orders a day's stops so the van arrives while shops are open.
 *
 * Travel time is the straight-line distance × 1.3 for real streets at 25 km/h, plus six minutes at
 * each stop; the van leaves the depot at the planned start and returns to it at the end. A plan
 * costs its travel minutes plus 30 minutes for every expected closed shop, Σ(1 − P(open at ETA)):
 * a wasted stop costs about as much as the detour to come back later.
 *
 * The search starts from nearest neighbour (and from the dispatcher's own order when there is one)
 * and improves each with 2-opt reversals and single-stop relocations until nothing helps. It is
 * deterministic: the same stops and start time always give the same order.
 */
final class RouteOptimizer
{
    public const SPEED_KMH = 25.0;

    public const DETOUR_FACTOR = 1.3;

    public const SERVICE_MINUTES = 6;

    public const CLOSED_PENALTY_MINUTES = 30.0;

    private const MAX_PASSES = 60;

    private const EPSILON = 1e-9;

    /** @var list<RouteStop> */
    private readonly array $stops;

    /** @var array<int, int> stop id => index */
    private readonly array $indexOf;

    /** @var list<list<float>> kilometres; index count($stops) is the depot */
    private array $km = [];

    /** @param list<RouteStop> $stops */
    public function __construct(
        private readonly GeoPoint $depot,
        private readonly int $startMinute,
        array $stops,
    ) {
        usort($stops, fn (RouteStop $a, RouteStop $b) => $a->id <=> $b->id);
        $this->stops = $stops;
        $indexOf = [];
        foreach ($stops as $i => $stop) {
            if (isset($indexOf[$stop->id])) {
                throw new InvalidArgumentException("Stop {$stop->id} is listed twice");
            }
            $indexOf[$stop->id] = $i;
        }
        $this->indexOf = $indexOf;

        $points = array_map(fn (RouteStop $s) => $s->point, $stops);
        $points[] = $depot;
        foreach ($points as $i => $a) {
            foreach ($points as $j => $b) {
                $this->km[$i][$j] = $i === $j ? 0.0 : $a->kmTo($b);
            }
        }
    }

    public static function minutesForKm(float $km): float
    {
        return $km * self::DETOUR_FACTOR / self::SPEED_KMH * 60;
    }

    /** @param list<int> $stopIds every stop exactly once, in driving order */
    public function evaluate(array $stopIds): RoutePlan
    {
        return $this->plan($this->indexes($stopIds));
    }

    public function nearestNeighbour(): RoutePlan
    {
        return $this->plan($this->nearestNeighbourOrder());
    }

    /** @param list<int>|null $currentOrder the order the dispatcher has now, as a second starting point */
    public function optimise(?array $currentOrder = null): RoutePlan
    {
        if (! $this->stops) {
            return $this->plan([]);
        }
        $best = $this->improve($this->nearestNeighbourOrder());
        if ($currentOrder !== null) {
            $fromCurrent = $this->improve($this->indexes($currentOrder));
            // On a tie keep the dispatcher's order: fewer surprises for the driver.
            if ($this->cost($fromCurrent) <= $this->cost($best) + self::EPSILON) {
                $best = $fromCurrent;
            }
        }

        return $this->plan($best);
    }

    /**
     * @param  list<int>  $stopIds
     * @return list<int>
     */
    private function indexes(array $stopIds): array
    {
        if (count($stopIds) !== count($this->stops) || count(array_unique($stopIds)) !== count($stopIds)) {
            throw new InvalidArgumentException('The order must list every stop exactly once');
        }

        return array_map(function (int $id) {
            if (! isset($this->indexOf[$id])) {
                throw new InvalidArgumentException("Unknown stop {$id}");
            }

            return $this->indexOf[$id];
        }, $stopIds);
    }

    /** @return list<int> indexes */
    private function nearestNeighbourOrder(): array
    {
        $left = array_keys($this->stops);
        $order = [];
        $here = count($this->stops);
        while ($left) {
            $nearest = null;
            foreach ($left as $candidate) {
                if ($nearest === null || $this->km[$here][$candidate] < $this->km[$here][$nearest] - self::EPSILON) {
                    $nearest = $candidate;
                }
            }
            $order[] = $nearest;
            $left = array_values(array_diff($left, [$nearest]));
            $here = $nearest;
        }

        return $order;
    }

    /**
     * First-improvement local search over 2-opt reversals and relocations.
     *
     * @param  list<int>  $order
     * @return list<int>
     */
    private function improve(array $order): array
    {
        $n = count($order);
        $best = $this->cost($order);
        for ($pass = 0; $pass < self::MAX_PASSES; $pass++) {
            $improved = false;
            for ($i = 0; $i < $n - 1; $i++) {
                for ($j = $i + 1; $j < $n; $j++) {
                    $candidate = $order;
                    array_splice($candidate, $i, $j - $i + 1, array_reverse(array_slice($order, $i, $j - $i + 1)));
                    $cost = $this->cost($candidate);
                    if ($cost < $best - self::EPSILON) {
                        [$order, $best, $improved] = [$candidate, $cost, true];
                    }
                }
            }
            for ($i = 0; $i < $n; $i++) {
                for ($j = 0; $j < $n; $j++) {
                    if ($i === $j) {
                        continue;
                    }
                    $candidate = $order;
                    $moved = array_splice($candidate, $i, 1);
                    array_splice($candidate, $j, 0, $moved);
                    $cost = $this->cost($candidate);
                    if ($cost < $best - self::EPSILON) {
                        [$order, $best, $improved] = [$candidate, $cost, true];
                    }
                }
            }
            if (! $improved) {
                break;
            }
        }

        return $order;
    }

    /** @param list<int> $order indexes */
    private function cost(array $order): float
    {
        $depot = count($this->stops);
        $clock = $this->startMinute;
        $travel = 0.0;
        $closed = 0.0;
        $here = $depot;
        foreach ($order as $i) {
            $leg = self::minutesForKm($this->km[$here][$i]);
            $travel += $leg;
            $clock += $leg;
            $closed += 1 - $this->stops[$i]->pOpen($clock);
            $clock += self::SERVICE_MINUTES;
            $here = $i;
        }
        $travel += self::minutesForKm($this->km[$here][$depot]);

        return $travel + self::CLOSED_PENALTY_MINUTES * $closed;
    }

    /** @param list<int> $order indexes */
    private function plan(array $order): RoutePlan
    {
        $depot = count($this->stops);
        $clock = (float) $this->startMinute;
        $km = 0.0;
        $closed = 0.0;
        $visits = [];
        $here = $depot;
        foreach ($order as $i) {
            $legKm = $this->km[$here][$i];
            $km += $legKm;
            $clock += self::minutesForKm($legKm);
            $p = $this->stops[$i]->pOpen($clock);
            $closed += 1 - $p;
            $visits[] = ['id' => $this->stops[$i]->id, 'eta' => (int) round($clock), 'pOpen' => round($p, 3), 'kmFromPrevious' => round($legKm, 2)];
            $clock += self::SERVICE_MINUTES;
            $here = $i;
        }
        $km += $this->km[$here][$depot];
        $clock += self::minutesForKm($this->km[$here][$depot]);
        $travel = self::minutesForKm($km);

        return new RoutePlan($visits, $km, $travel, $closed, (int) round($clock), $travel + self::CLOSED_PENALTY_MINUTES * $closed);
    }
}
