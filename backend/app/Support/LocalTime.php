<?php

namespace App\Support;

use App\Models\Organization;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * The organisation's wall clock. Timestamps are stored in UTC; weekdays, minutes of the day
 * and trip dates are always the distributor's local ones.
 */
final class LocalTime
{
    public function __construct(public readonly string $timezone) {}

    public static function for(Organization $organization): self
    {
        return new self($organization->timezone ?: config('product.default_timezone'));
    }

    public function now(): CarbonImmutable
    {
        return CarbonImmutable::now($this->timezone);
    }

    public function today(): CarbonImmutable
    {
        return $this->now()->startOfDay();
    }

    /** A calendar day ("2026-09-24") at local midnight. */
    public function date(string $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date, $this->timezone)->startOfDay();
    }

    /** @return array{0: int, 1: int} ISO weekday and minutes since local midnight */
    public function weekdayMinute(CarbonInterface $instant): array
    {
        $local = CarbonImmutable::instance($instant)->setTimezone($this->timezone);

        return [$local->dayOfWeekIso, $local->hour * 60 + $local->minute];
    }

    /** The instant of a local date plus minutes after midnight. */
    public function at(CarbonInterface $date, int $minuteOfDay): CarbonImmutable
    {
        return CarbonImmutable::parse($date->toDateString(), $this->timezone)->startOfDay()->addMinutes($minuteOfDay);
    }
}
