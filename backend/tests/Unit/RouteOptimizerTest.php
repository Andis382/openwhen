<?php

namespace Tests\Unit;

use App\Routing\GeoPoint;
use App\Routing\RouteOptimizer;
use App\Routing\RouteStop;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class RouteOptimizerTest extends TestCase
{
    private GeoPoint $depot;

    protected function setUp(): void
    {
        $this->depot = new GeoPoint(41.3372, 19.7968);
    }

    /** Open (P = 0.95) from $opens, shut (P = 0.05) before. */
    private function stop(int $id, float $lat, float $lng, string $opens = '06:00'): RouteStop
    {
        [$h, $m] = array_map('intval', explode(':', $opens));
        $opensAt = $h * 60 + $m;

        return new RouteStop($id, new GeoPoint($lat, $lng), array_map(fn (int $slot) => 360 + $slot * 30 >= $opensAt ? 0.95 : 0.05, range(0, 29)));
    }

    /** @return list<RouteStop> */
    private function randomStops(int $seed, int $count): array
    {
        mt_srand($seed);
        $opens = ['06:00', '07:00', '08:00', '09:00', '10:00', '11:00'];
        $stops = [];
        for ($i = 1; $i <= $count; $i++) {
            $stops[] = $this->stop($i, 41.31 + mt_rand(0, 4000) / 100000, 19.78 + mt_rand(0, 7000) / 100000, $opens[mt_rand(0, count($opens) - 1)]);
        }

        return $stops;
    }

    public function test_arrival_times_follow_distance_speed_and_service_time(): void
    {
        $a = $this->stop(1, 41.3372, 19.8168);
        $b = $this->stop(2, 41.3472, 19.8168);
        $plan = (new RouteOptimizer($this->depot, 420, [$a, $b]))->evaluate([1, 2]);

        $toA = RouteOptimizer::minutesForKm($this->depot->kmTo($a->point));
        $aToB = RouteOptimizer::minutesForKm($a->point->kmTo($b->point));
        $home = RouteOptimizer::minutesForKm($b->point->kmTo($this->depot));

        $this->assertSame((int) round(420 + $toA), $plan->visits[0]['eta']);
        $this->assertSame((int) round(420 + $toA + 6 + $aToB), $plan->visits[1]['eta']);
        $this->assertSame((int) round(420 + $toA + 6 + $aToB + 6 + $home), $plan->finishMinute);
        $this->assertEqualsWithDelta($toA + $aToB + $home, $plan->travelMinutes, 1e-6);
        $this->assertEqualsWithDelta(1.3 * 60 / 25, RouteOptimizer::minutesForKm(1), 1e-9, '1 km of straight line takes 3.12 minutes');
    }

    public function test_cost_is_travel_plus_thirty_minutes_per_expected_closed_stop(): void
    {
        $plan = (new RouteOptimizer($this->depot, 420, [$this->stop(1, 41.34, 19.80, '10:00')]))->evaluate([1]);

        $this->assertEqualsWithDelta(0.95, $plan->expectedClosed, 1e-9);
        $this->assertEqualsWithDelta($plan->travelMinutes + 30 * 0.95, $plan->cost, 1e-9);
    }

    public function test_improves_on_or_equals_nearest_neighbour(): void
    {
        foreach ([3, 17, 42, 99, 2026] as $seed) {
            $optimizer = new RouteOptimizer($this->depot, 420, $this->randomStops($seed, 14));

            $this->assertLessThanOrEqual($optimizer->nearestNeighbour()->cost + 1e-9, $optimizer->optimise()->cost, "seed {$seed}");
        }
    }

    public function test_never_worse_than_the_dispatchers_order(): void
    {
        $stops = $this->randomStops(7, 12);
        $optimizer = new RouteOptimizer($this->depot, 420, $stops);
        $mine = [12, 3, 5, 1, 9, 2, 11, 4, 8, 6, 10, 7];

        $this->assertLessThanOrEqual($optimizer->evaluate($mine)->cost + 1e-9, $optimizer->optimise($mine)->cost);
    }

    /** A shop next to the depot that opens at 10:00, and three open shops further out. */
    private function lateOpenerRound(): array
    {
        return [
            $this->stop(1, 41.3380, 19.7990, '10:00'),
            $this->stop(2, 41.3300, 19.8200),
            $this->stop(3, 41.3200, 19.8350),
            $this->stop(4, 41.3150, 19.8450),
        ];
    }

    public function test_sends_the_late_opener_to_the_end(): void
    {
        $optimizer = new RouteOptimizer($this->depot, 570, $this->lateOpenerRound());

        $before = $optimizer->nearestNeighbour();
        $after = $optimizer->optimise();

        $this->assertSame(1, $before->order()[0], 'nearest neighbour starts next door, at 09:31');
        $this->assertSame(1, $after->order()[3]);
        $this->assertGreaterThanOrEqual(600, $after->visits[3]['eta'], 'reached after it opens');
        $this->assertLessThan($before->expectedClosed - 0.8, $after->expectedClosed);
        $this->assertLessThan($before->cost, $after->cost);
    }

    public function test_respects_the_start_time(): void
    {
        $stops = $this->randomStops(11, 10);
        foreach ([420, 690] as $start) {
            $plan = (new RouteOptimizer($this->depot, $start, $stops))->optimise();
            $first = $stops[array_search($plan->order()[0], array_map(fn (RouteStop $s) => $s->id, $stops), true)];

            $this->assertSame((int) round($start + RouteOptimizer::minutesForKm($this->depot->kmTo($first->point))), $plan->visits[0]['eta']);
            $etas = array_map(fn (array $v) => $v['eta'], $plan->visits);
            $sorted = $etas;
            sort($sorted);
            $this->assertSame($sorted, $etas, 'arrival times only move forward');
        }
    }

    public function test_when_everyone_is_open_distance_decides(): void
    {
        $plan = (new RouteOptimizer($this->depot, 600, $this->lateOpenerRound()))->optimise();

        $this->assertSame(1, $plan->order()[0], 'leaving at 10:00 the late opener next door is already open');
    }

    public function test_is_deterministic(): void
    {
        $stops = $this->randomStops(5, 15);
        $shuffled = $stops;
        mt_srand(1);
        shuffle($shuffled);

        $a = (new RouteOptimizer($this->depot, 420, $stops))->optimise();
        $b = (new RouteOptimizer($this->depot, 420, $stops))->optimise();
        $c = (new RouteOptimizer($this->depot, 420, $shuffled))->optimise();

        $this->assertSame($a->order(), $b->order());
        $this->assertSame($a->order(), $c->order(), 'input order does not matter');
    }

    public function test_an_empty_trip_returns_straight_home(): void
    {
        $plan = (new RouteOptimizer($this->depot, 420, []))->optimise();

        $this->assertSame([], $plan->visits);
        $this->assertSame(420, $plan->finishMinute);
        $this->assertSame(0.0, $plan->km);
    }

    public function test_an_order_must_list_every_stop_once(): void
    {
        $optimizer = new RouteOptimizer($this->depot, 420, [$this->stop(1, 41.34, 19.80), $this->stop(2, 41.33, 19.81)]);

        $this->expectException(InvalidArgumentException::class);
        $optimizer->evaluate([1, 1]);
    }
}
