<?php

namespace Tests\Unit;

use App\Hours\DeclaredHours;
use App\Hours\OpeningHoursModel;
use App\Hours\Slots;
use PHPUnit\Framework\TestCase;

class OpeningHoursModelTest extends TestCase
{
    use SightingHelpers;

    private const EPS = 1e-9;

    public function test_the_prior_follows_the_declared_hours(): void
    {
        $model = OpeningHoursModel::learn(DeclaredHours::fromArray(['1' => [['08:00', '13:00']], '5' => []]));

        $this->assertSame([2.0, 1.0], $model->posterior(1, Slots::index(600)));
        $this->assertEqualsWithDelta(2 / 3, $model->probability(1, 600), self::EPS);
        $this->assertSame([1.0, 2.0], $model->posterior(1, Slots::index(420)), 'before 08:00 is declared closed');
        $this->assertSame([1.0, 2.0], $model->posterior(5, Slots::index(600)), 'Friday is declared closed all day');
        $this->assertSame([1.0, 1.0], $model->posterior(2, Slots::index(600)), 'Tuesday is unknown');
        $this->assertEqualsWithDelta(0.5, $model->probability(2, 600), self::EPS);
    }

    public function test_a_sighting_weighs_one_in_its_slot_and_half_in_each_neighbour(): void
    {
        $model = OpeningHoursModel::learn(DeclaredHours::unknown(), [$this->saw(1, '09:40', true)]);
        $slot = Slots::index(580);

        $this->assertSame([2.0, 1.0], $model->posterior(1, $slot));
        $this->assertSame([1.5, 1.0], $model->posterior(1, $slot - 1));
        $this->assertSame([1.5, 1.0], $model->posterior(1, $slot + 1));
        $this->assertSame([1.0, 1.0], $model->posterior(1, $slot + 2));
        $this->assertSame(1.0, $model->confidence(1, $slot));
        $this->assertSame(0.5, $model->confidence(1, $slot - 1));
        $this->assertSame(0.0, $model->confidence(1, $slot + 2));
    }

    public function test_a_closed_sighting_adds_to_beta(): void
    {
        $model = OpeningHoursModel::learn(DeclaredHours::unknown(), [$this->saw(3, '10:05', false)]);
        $slot = Slots::index(605);

        $this->assertSame([1.0, 2.0], $model->posterior(3, $slot));
        $this->assertSame([1.0, 1.5], $model->posterior(3, $slot + 1));
        $this->assertEqualsWithDelta(1 / 3, $model->slotProbability(3, $slot), self::EPS);
    }

    public function test_p_open_is_alpha_over_alpha_plus_beta(): void
    {
        $model = OpeningHoursModel::learn($this->everyDay('07:00', '20:00'), [
            $this->saw(2, '08:10', false),
            $this->saw(2, '08:15', false),
            $this->saw(2, '08:20', false),
        ]);
        $slot = Slots::index(490);

        // declared open 2:1, three closed sightings in the slot, 1.5 more in each neighbour
        $this->assertEqualsWithDelta(2 / (2 + 4), $model->slotProbability(2, $slot), self::EPS);
        $this->assertEqualsWithDelta(2 / (2 + 2.5), $model->slotProbability(2, $slot + 1), self::EPS);
        $this->assertSame(3.0, $model->confidence(2, $slot));
    }

    public function test_edge_slots_only_have_one_neighbour(): void
    {
        $model = OpeningHoursModel::learn(DeclaredHours::unknown(), [
            $this->saw(1, '06:10', true),
            $this->saw(1, '20:50', false),
        ]);

        $this->assertSame([2.0, 1.0], $model->posterior(1, 0));
        $this->assertSame([1.5, 1.0], $model->posterior(1, 1));
        $this->assertSame([1.0, 2.0], $model->posterior(1, Slots::COUNT - 1));
        $this->assertSame([1.0, 1.5], $model->posterior(1, Slots::COUNT - 2));
    }

    public function test_sightings_outside_the_modelled_day_are_ignored(): void
    {
        $model = OpeningHoursModel::learn(DeclaredHours::unknown(), [
            $this->saw(1, '05:59', true),
            $this->saw(1, '21:00', true),
            $this->saw(1, '23:30', false),
        ]);

        for ($slot = 0; $slot < Slots::COUNT; $slot++) {
            $this->assertSame([1.0, 1.0], $model->posterior(1, $slot));
        }
        $this->assertSame(OpeningHoursModel::OUTSIDE_HOURS_P, $model->probability(1, 330));
        $this->assertSame(OpeningHoursModel::OUTSIDE_HOURS_P, $model->probability(1, 1260));
    }

    public function test_weekdays_learn_independently(): void
    {
        $model = OpeningHoursModel::learn(DeclaredHours::unknown(), [$this->saw(1, '09:00', true)]);

        $this->assertSame([1.0, 1.0], $model->posterior(2, Slots::index(540)));
        $this->assertSame([1.0, 1.0], $model->posterior(7, Slots::index(540)));
    }

    public function test_declared_hours_alone_are_not_reliable(): void
    {
        $model = OpeningHoursModel::learn($this->everyDay('07:00', '20:00'));

        $this->assertFalse($model->isReliablyOpen(1, Slots::index(600)), '2:1 is P = 0.67, below 0.7');
        $this->assertNull($model->nextReliablyOpen(1, 600));
    }

    public function test_next_reliably_open_moment(): void
    {
        $sightings = array_merge(
            $this->sawOn([1, 2], ['10:10', '10:40', '11:10'], true),
        );
        $model = OpeningHoursModel::learn($this->everyDay('10:00', '20:00'), $sightings);

        $this->assertSame(['weekday' => 1, 'minute' => 600], $model->nextReliablyOpen(1, 420));
        $this->assertSame(['weekday' => 1, 'minute' => 630], $model->nextReliablyOpen(1, 630));
        $this->assertSame(['weekday' => 2, 'minute' => 600], $model->nextReliablyOpen(1, 1290), 'after closing: the next morning');
    }

    public function test_reliably_open_until_and_last_open_before(): void
    {
        $model = OpeningHoursModel::learn(DeclaredHours::unknown(), $this->sawOn([4], ['08:10', '08:40', '09:10', '09:40'], true));

        // 07:30 and 10:00 only get half weights: 1.5 / 2.5 = 0.6
        $this->assertSame(600, $model->reliablyOpenUntil(4, 500));
        $this->assertNull($model->reliablyOpenUntil(4, 450));
        $this->assertSame(600, $model->lastReliablyOpenBefore(4, 700));
        $this->assertNull($model->lastReliablyOpenBefore(4, 470));
    }

    public function test_the_grid_has_seven_days_of_thirty_slots(): void
    {
        $grid = OpeningHoursModel::learn(DeclaredHours::unknown(), [$this->saw(6, '12:00', false)])->toArray();

        $this->assertCount(7, $grid);
        $this->assertCount(Slots::COUNT, $grid[5]['p']);
        $this->assertSame(6, $grid[5]['weekday']);
        $this->assertSame(0.333, $grid[5]['p'][Slots::index(720)]);
        $this->assertSame(1.0, $grid[5]['n'][Slots::index(720)]);
    }
}
