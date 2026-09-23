<?php

namespace Tests\Unit;

use App\Hours\DeclaredHours;
use App\Hours\Sighting;

trait SightingHelpers
{
    private function saw(int $weekday, string $time, bool $open): Sighting
    {
        return new Sighting($weekday, DeclaredHours::parseTime($time), $open);
    }

    /**
     * @param  list<int>  $weekdays
     * @param  list<string>  $times
     * @return list<Sighting>
     */
    private function sawOn(array $weekdays, array $times, bool $open): array
    {
        $sightings = [];
        foreach ($weekdays as $day) {
            foreach ($times as $time) {
                $sightings[] = $this->saw($day, $time, $open);
            }
        }

        return $sightings;
    }

    /** The same hours on every day of the week. */
    private function everyDay(string $open, string $close): DeclaredHours
    {
        return DeclaredHours::fromArray(array_fill_keys(range(1, 7), [[$open, $close]]));
    }
}
