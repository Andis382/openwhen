<?php

namespace Tests\Unit;

use App\Models\Shop;
use App\Models\Visit;
use App\Services\OpenWindowEstimator;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

/**
 * The arithmetic, on made-up visits, with no database near it.
 *
 * Every claim this product makes is downstream of these numbers, and every one
 * of them is the kind of thing that can be quietly wrong for months: a window
 * an hour too wide sends a van to a shutter once a week and nobody ever traces
 * it back to a threshold.
 */
class OpenWindowEstimatorTest extends TestCase
{
    private OpenWindowEstimator $estimator;
    private Shop $shop;

    protected function setUp(): void
    {
        parent::setUp();
        $this->estimator = new OpenWindowEstimator();
        $this->shop = new Shop(['name' => 'Market Vera']);
    }

    /** @param array<int, array{0:int, 1:string}> $rows hour and outcome */
    private function visits(array $rows): Collection
    {
        return collect($rows)->map(fn (array $row) => new Visit(['hour' => $row[0], 'outcome' => $row[1]]));
    }

    private function repeat(int $hour, string $outcome, int $times): array
    {
        return array_fill(0, $times, [$hour, $outcome]);
    }

    public function test_a_weekday_with_almost_no_visits_says_nothing_at_all(): void
    {
        $profile = $this->estimator->profile($this->shop, 1, $this->visits([
            [9, Visit::DELIVERED],
            [10, Visit::DELIVERED],
        ]));

        $this->assertFalse($profile['known']);
        $this->assertNull($profile['open_from']);
        $this->assertSame([], $profile['rules']);
        $this->assertNull($this->estimator->bestHour($this->shop, 1, $this->visits([[9, Visit::DELIVERED]])));
    }

    public function test_a_steadily_visited_shop_gets_a_window(): void
    {
        $rows = array_merge(
            $this->repeat(8, Visit::DELIVERED, 3),
            $this->repeat(12, Visit::DELIVERED, 3),
            $this->repeat(17, Visit::DELIVERED, 3),
        );

        $profile = $this->estimator->profile($this->shop, 2, $this->visits($rows));

        $this->assertTrue($profile['known']);
        $this->assertSame(8, $profile['open_from']);
        $this->assertSame(17, $profile['open_to']);
    }

    /**
     * A refusal is not a closure. Somebody was standing there to say no, which
     * is exactly the evidence the estimator wants and exactly the distinction
     * a sales-outcome field throws away.
     */
    public function test_a_refusal_counts_as_the_shop_being_open(): void
    {
        $rows = array_merge(
            $this->repeat(9, Visit::REFUSED, 3),
            $this->repeat(10, Visit::OWNER_ABSENT, 3),
        );

        $profile = $this->estimator->profile($this->shop, 3, $this->visits($rows));

        $this->assertSame(9, $profile['open_from']);
        $this->assertSame(10, $profile['open_to']);
    }

    /** The one rule that keeps a bad morning from becoming a fact. */
    public function test_one_unlucky_closed_visit_does_not_become_a_rule(): void
    {
        $rows = array_merge(
            [[9, Visit::CLOSED]],
            $this->repeat(11, Visit::DELIVERED, 4),
            $this->repeat(15, Visit::DELIVERED, 3),
        );

        $profile = $this->estimator->profile($this->shop, 1, $this->visits($rows));

        $this->assertTrue($profile['known']);
        $this->assertSame([], $profile['rules'], 'one closed visit is not evidence of anything');
    }

    public function test_a_shop_that_is_never_open_early_says_so(): void
    {
        $rows = array_merge(
            $this->repeat(8, Visit::CLOSED, 4),
            $this->repeat(11, Visit::DELIVERED, 4),
            $this->repeat(16, Visit::DELIVERED, 3),
        );

        $profile = $this->estimator->profile($this->shop, 1, $this->visits($rows));

        $rules = collect($profile['rules']);
        $this->assertTrue($rules->contains(fn (array $rule) => $rule['key'] === 'not_before' && $rule['hour'] === 11));
    }

    /** The lunch closure nobody ever wrote down, which is the expensive one. */
    public function test_a_hole_in_the_middle_of_the_day_is_found(): void
    {
        $rows = array_merge(
            $this->repeat(9, Visit::DELIVERED, 3),
            $this->repeat(13, Visit::CLOSED, 4),
            $this->repeat(17, Visit::DELIVERED, 3),
        );

        $profile = $this->estimator->profile($this->shop, 2, $this->visits($rows));

        $rules = collect($profile['rules']);
        $this->assertTrue(
            $rules->contains(fn (array $rule) => $rule['key'] === 'shut_around' && $rule['hour'] === 13),
            'a reliably shut hour between two open ones is the whole product'
        );
    }

    public function test_an_hour_visited_once_is_not_read_at_all(): void
    {
        $rows = array_merge(
            [[7, Visit::DELIVERED]],
            $this->repeat(12, Visit::DELIVERED, 4),
        );

        $profile = $this->estimator->profile($this->shop, 4, $this->visits($rows));

        $this->assertFalse($profile['hours'][7]['known']);
        $this->assertSame(12, $profile['open_from'], 'a single visit at seven must not widen the window');
    }

    /** Nothing seen is 0.5 — genuinely unknown, not "probably open". */
    public function test_an_unvisited_hour_sits_exactly_on_the_fence(): void
    {
        $profile = $this->estimator->profile($this->shop, 5, $this->visits($this->repeat(12, Visit::DELIVERED, 4)));

        $this->assertSame(0.5, $profile['hours'][6]['p']);
        $this->assertFalse($profile['hours'][6]['known']);
    }

    public function test_smoothing_keeps_one_closed_visit_from_reading_as_certainty(): void
    {
        $rows = array_merge(
            $this->repeat(9, Visit::CLOSED, 1),
            $this->repeat(12, Visit::DELIVERED, 4),
        );

        $profile = $this->estimator->profile($this->shop, 6, $this->visits($rows));

        // (0 + 1) / (1 + 2) = 0.3333, not 0.
        $this->assertGreaterThan(0.0, $profile['hours'][9]['p']);
        $this->assertLessThan(0.5, $profile['hours'][9]['p']);
    }

    public function test_the_best_hour_is_the_middle_of_the_open_stretch(): void
    {
        $rows = array_merge(
            $this->repeat(8, Visit::DELIVERED, 3),
            $this->repeat(12, Visit::DELIVERED, 3),
            $this->repeat(16, Visit::DELIVERED, 3),
        );

        // The edges of a window are where a van is most likely to arrive five
        // minutes the wrong side of it.
        $this->assertSame(12, $this->estimator->bestHour($this->shop, 1, $this->visits($rows)));
    }

    public function test_an_owner_who_is_away_is_an_open_shop_and_a_wasted_stop(): void
    {
        $visits = $this->visits([
            [9, Visit::DELIVERED],
            [10, Visit::OWNER_ABSENT],
            [11, Visit::CLOSED],
            [12, Visit::REFUSED],
        ]);

        $wasted = $this->estimator->wasted($visits);

        $this->assertSame(4, $wasted['visits']);
        $this->assertSame(2, $wasted['wasted'], 'a closed shutter and an absent owner both cost a stop');
        $this->assertSame(50.0, $wasted['rate']);
    }

    public function test_hours_outside_the_trading_day_are_ignored(): void
    {
        $rows = array_merge(
            $this->repeat(3, Visit::CLOSED, 4),
            $this->repeat(12, Visit::DELIVERED, 4),
        );

        $profile = $this->estimator->profile($this->shop, 1, $this->visits($rows));

        $this->assertArrayNotHasKey(3, $profile['hours']);
        $this->assertSame(12, $profile['open_from']);
    }
}
