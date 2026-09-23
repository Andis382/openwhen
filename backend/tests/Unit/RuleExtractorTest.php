<?php

namespace Tests\Unit;

use App\Hours\HoursRule;
use App\Hours\OpeningHoursModel;
use App\Hours\RuleExtractor;
use PHPUnit\Framework\TestCase;

class RuleExtractorTest extends TestCase
{
    use SightingHelpers;

    /** @return list<HoursRule> */
    private function rules(array $sightings, ?\App\Hours\DeclaredHours $declared = null): array
    {
        $model = OpeningHoursModel::learn($declared ?? $this->everyDay('07:00', '20:00'), $sightings);

        return (new RuleExtractor)->extract($model, $sightings);
    }

    public function test_a_late_monday_opener(): void
    {
        $sightings = array_merge(
            $this->sawOn([1], ['07:10', '08:00', '08:40', '09:20', '09:50'], false),
            $this->sawOn([1], ['10:10', '10:40', '11:30', '12:30'], true),
        );

        $rules = $this->rules($sightings);

        $this->assertCount(1, $rules);
        $this->assertSame(
            ['type' => 'opens_after', 'weekdays' => [1], 'from' => '10:00', 'to' => null, 'open' => 0, 'total' => 5],
            $rules[0]->toArray(),
        );
    }

    public function test_a_rule_needs_three_sightings_behind_it(): void
    {
        $sightings = array_merge(
            $this->sawOn([1], ['08:40', '09:50'], false),
            $this->sawOn([1], ['10:10', '10:40', '11:30', '12:30'], true),
        );

        $this->assertSame([], $this->rules($sightings));
    }

    public function test_too_many_open_sightings_in_the_window_cancel_the_rule(): void
    {
        $sightings = array_merge(
            $this->sawOn([1], ['08:00', '08:40', '09:50'], false),
            $this->sawOn([1], ['09:20'], true),
            $this->sawOn([1], ['10:10', '10:40', '11:30', '12:30'], true),
        );

        $this->assertSame([], $this->rules($sightings), 'one open in four is more than one in five');
    }

    public function test_a_lunch_closure_shared_by_the_working_week(): void
    {
        $weekdays = [1, 2, 3, 4, 5];
        $sightings = array_merge(
            $this->sawOn($weekdays, ['12:10', '12:40', '15:10', '15:40'], true),
            $this->sawOn($weekdays, ['13:05', '13:20', '13:45', '14:05', '14:25', '14:50'], false),
        );

        $rules = $this->rules($sightings);

        $this->assertCount(1, $rules);
        $this->assertSame(
            ['type' => 'closed_window', 'weekdays' => $weekdays, 'from' => '13:00', 'to' => '15:00', 'open' => 0, 'total' => 30],
            $rules[0]->toArray(),
        );
    }

    public function test_days_half_an_hour_apart_merge_into_the_cautious_rule(): void
    {
        $sightings = array_merge(
            $this->sawOn([2], ['07:10', '07:40', '08:10', '08:40', '09:10'], false),
            $this->sawOn([2], ['09:40', '10:10', '10:40'], true),
            $this->sawOn([4], ['07:10', '07:40', '08:10', '08:40'], false),
            $this->sawOn([4], ['09:10', '09:40', '10:10'], true),
        );

        $rules = $this->rules($sightings);

        $this->assertCount(1, $rules);
        $this->assertSame(
            ['type' => 'opens_after', 'weekdays' => [2, 4], 'from' => '09:00', 'to' => null, 'open' => 0, 'total' => 8],
            $rules[0]->toArray(),
            'Tuesday alone would say 09:30; together they claim only what both days back',
        );
    }

    public function test_a_day_closed_at_every_visit(): void
    {
        $sightings = array_merge(
            $this->sawOn([5], ['07:30', '10:00', '12:00', '16:00'], false),
            $this->sawOn([4], ['07:30', '10:00'], true),
        );

        $rules = $this->rules($sightings);

        $this->assertSame(
            ['type' => 'closed_day', 'weekdays' => [5], 'from' => null, 'to' => null, 'open' => 0, 'total' => 4],
            $rules[0]->toArray(),
        );
    }

    public function test_one_open_visit_in_five_still_counts_as_closed(): void
    {
        $sightings = array_merge(
            $this->sawOn([5], ['07:30', '10:00', '12:00', '16:00'], false),
            $this->sawOn([5], ['18:00'], true),
        );

        $this->assertSame('closed_day', $this->rules($sightings)[0]->type);
        $this->assertSame(1, $this->rules($sightings)[0]->open);
    }

    public function test_two_closed_visits_do_not_make_a_closed_day(): void
    {
        $this->assertSame([], $this->rules($this->sawOn([5], ['07:30', '10:00'], false)));
    }

    public function test_closing_early(): void
    {
        $sightings = array_merge(
            $this->sawOn([6], ['09:10', '10:10', '11:10', '12:10', '13:10'], true),
            $this->sawOn([6], ['14:40', '15:30', '17:00'], false),
        );

        $rules = $this->rules($sightings);

        $this->assertCount(1, $rules);
        $this->assertSame('closed_after', $rules[0]->type);
        $this->assertSame('14:00', $rules[0]->toArray()['from']);
        $this->assertSame([0, 3], [$rules[0]->open, $rules[0]->total]);
    }

    public function test_rules_come_in_a_stable_order(): void
    {
        $sightings = array_merge(
            $this->sawOn([5], ['07:30', '10:00', '12:00'], false),
            $this->sawOn([1], ['07:10', '08:00', '08:40', '09:20', '09:50'], false),
            $this->sawOn([1], ['10:10', '10:40', '11:30', '12:30'], true),
        );

        $this->assertSame(['closed_day', 'opens_after'], array_map(fn (HoursRule $r) => $r->type, $this->rules($sightings)));
    }
}
