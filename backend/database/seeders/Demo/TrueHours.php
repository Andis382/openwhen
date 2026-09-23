<?php

namespace Database\Seeders\Demo;

use App\Hours\Slots;

/**
 * The demo's hidden truth: when each kind of shop really is open. Drivers' outcomes are drawn
 * from these hours, and the app has to rediscover them from the visits alone.
 */
final class TrueHours
{
    /** Mon–Sat 07:00–21:00, Sunday morning. */
    public const STANDARD = 'standard';

    /** Bakeries and pastry shops: 06:30–22:00 every day. */
    public const EARLY = 'early';

    /** Standard, but Mondays not before 10:00 (restocking at the wholesaler). */
    public const LATE_MONDAY = 'late_monday';

    /** Never before 09:00; closed on Sundays. */
    public const LATE = 'late';

    /** Weekdays shut 13:00–15:00; Saturday until 14:00; closed on Sundays. */
    public const LUNCH = 'lunch';

    /** Standard, but shut 12:00–14:00 on Fridays. */
    public const FRIDAY_PRAYER = 'friday_prayer';

    /** Standard, but closed all day on Fridays. */
    public const FRIDAY_CLOSED = 'friday_closed';

    /** Standard, but shutter down 09:00–10:30 on Tuesdays and Thursdays while the owner is at the bank. */
    public const BANK_MORNINGS = 'bank_mornings';

    /** Saturday until 14:00, closed on Sundays. */
    public const SATURDAY_EARLY = 'saturday_early';

    /** @return list<array{0: int, 1: int}> */
    public static function intervals(string $pattern, int $weekday): array
    {
        $standard = $weekday === 7 ? [[480, 840]] : [[420, 1260]];

        return match ($pattern) {
            self::STANDARD => $standard,
            self::EARLY => [[390, 1320]],
            self::LATE_MONDAY => $weekday === 1 ? [[600, 1260]] : $standard,
            self::LATE => $weekday === 7 ? [] : [[540, 1260]],
            self::LUNCH => match (true) {
                $weekday <= 5 => [[450, 780], [900, 1230]],
                $weekday === 6 => [[450, 840]],
                default => [],
            },
            self::FRIDAY_PRAYER => $weekday === 5 ? [[420, 720], [840, 1260]] : $standard,
            self::FRIDAY_CLOSED => $weekday === 5 ? [] : $standard,
            self::BANK_MORNINGS => in_array($weekday, [2, 4], true) ? [[420, 540], [630, 1260]] : $standard,
            self::SATURDAY_EARLY => match ($weekday) {
                6 => [[420, 840]],
                7 => [],
                default => [[420, 1260]],
            },
        };
    }

    public static function isOpen(string $pattern, int $weekday, int $minute): bool
    {
        foreach (self::intervals($pattern, $weekday) as [$open, $close]) {
            if ($minute >= $open && $minute < $close) {
                return true;
            }
        }

        return false;
    }

    /**
     * The hours as a shop would state them.
     *
     * @param  list<int>|null  $onlyDays  leave the other days unknown
     */
    public static function declared(string $pattern, ?array $onlyDays = null): array
    {
        $days = [];
        foreach ($onlyDays ?? range(1, 7) as $day) {
            $days[(string) $day] = array_map(fn (array $i) => [Slots::format($i[0]), Slots::format($i[1])], self::intervals($pattern, $day));
        }

        return $days;
    }
}
