<?php

namespace Tests\Unit;

use App\Hours\DeclaredHours;
use App\Hours\HoursComparison;
use App\Hours\OpeningHoursModel;
use App\Hours\RuleExtractor;
use PHPUnit\Framework\TestCase;

class HoursComparisonTest extends TestCase
{
    use SightingHelpers;

    private function compare(DeclaredHours $declared, array $sightings): array
    {
        $model = OpeningHoursModel::learn($declared, $sightings);

        return (new HoursComparison)->compare($model, $sightings, (new RuleExtractor)->extract($model, $sightings));
    }

    public function test_a_learned_closed_window_inside_declared_hours_is_a_mismatch(): void
    {
        $sightings = array_merge(
            $this->sawOn([1], ['07:10', '08:00', '08:40', '09:20', '09:50'], false),
            $this->sawOn([1], ['10:10', '10:40', '11:30', '12:30'], true),
        );

        $monday = $this->compare($this->everyDay('07:00', '20:00'), $sightings)[0];

        $this->assertSame('mismatch', $monday['status']);
        $this->assertSame([['from' => '07:00', 'to' => '10:00', 'declaredOpen' => true, 'open' => 0, 'total' => 5, 'days' => [1]]], $monday['conflicts']);
        $this->assertSame('10:00', $monday['observed'][0][0]);
        $this->assertSame(9, $monday['visits']);
    }

    public function test_open_on_a_declared_closed_day_is_a_mismatch(): void
    {
        $declared = DeclaredHours::fromArray(['7' => []]);

        $sunday = $this->compare($declared, $this->sawOn([7], ['09:10', '09:40', '10:10'], true))[6];

        $this->assertSame('mismatch', $sunday['status']);
        $this->assertSame([['from' => '06:00', 'to' => '21:00', 'declaredOpen' => false, 'open' => 3, 'total' => 3, 'days' => [7]]], $sunday['conflicts']);
    }

    public function test_agreeing_visits_match(): void
    {
        $days = $this->compare($this->everyDay('07:00', '20:00'), $this->sawOn([2], ['08:10', '09:10', '10:10'], true));

        $this->assertSame('match', $days[1]['status']);
        $this->assertSame([], $days[1]['conflicts']);
    }

    public function test_few_visits_say_nothing_yet(): void
    {
        $days = $this->compare($this->everyDay('07:00', '20:00'), $this->sawOn([3], ['07:10', '07:20'], false));

        $this->assertSame('too_few', $days[2]['status']);
        $this->assertSame([], $days[2]['observed']);
    }

    public function test_undeclared_days_show_what_was_seen(): void
    {
        $days = $this->compare(DeclaredHours::unknown(), $this->sawOn([6], ['09:10', '09:40', '10:10'], true));

        $this->assertSame('undeclared', $days[5]['status']);
        $this->assertNull($days[5]['declared']);
        $this->assertSame([['09:00', '10:30']], $days[5]['observed']);
    }
}
