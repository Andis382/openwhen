<?php

namespace App\Hours;

/**
 * The modelled trading day: thirty half-hour slots from 06:00 to 21:00 for each ISO weekday
 * (1 = Monday … 7 = Sunday). Minutes are counted from local midnight.
 */
final class Slots
{
    public const FIRST_MINUTE = 360;

    public const LAST_MINUTE = 1260;

    public const LENGTH = 30;

    public const COUNT = 30;

    /** The slot a minute falls into, or null before 06:00 and from 21:00 on. */
    public static function index(int $minuteOfDay): ?int
    {
        if ($minuteOfDay < self::FIRST_MINUTE || $minuteOfDay >= self::LAST_MINUTE) {
            return null;
        }

        return intdiv($minuteOfDay - self::FIRST_MINUTE, self::LENGTH);
    }

    public static function start(int $slot): int
    {
        return self::FIRST_MINUTE + $slot * self::LENGTH;
    }

    public static function end(int $slot): int
    {
        return self::start($slot) + self::LENGTH;
    }

    public static function middle(int $slot): int
    {
        return self::start($slot) + intdiv(self::LENGTH, 2);
    }

    /** "07:30" */
    public static function format(int $minuteOfDay): string
    {
        return sprintf('%02d:%02d', intdiv($minuteOfDay, 60), $minuteOfDay % 60);
    }
}
