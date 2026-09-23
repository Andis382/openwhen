<?php

namespace App\Hours;

/**
 * What we believe about a shop's real opening hours.
 *
 * Every half-hour slot of every weekday is a Beta(α, β) posterior over "the shutter is up".
 * The prior comes from the declared hours (declared open 2:1, declared closed 1:2, unknown 1:1),
 * so a shop with no history behaves as it says it does. Each sighting then adds weight 1 to
 * its own slot and 0.5 to the slot on either side, to α when the shop was open and to β when
 * it was shut: a visit at 09:40 says something about 09:15 too.
 */
final class OpeningHoursModel
{
    public const RELIABLY_OPEN = 0.7;

    public const RELIABLY_CLOSED = 0.3;

    public const NEIGHBOUR_WEIGHT = 0.5;

    /** Before 06:00 and from 21:00 there are no slots; a van arriving then finds most shops shut. */
    public const OUTSIDE_HOURS_P = 0.1;

    /** @var array<int, array<int, float>> */
    private array $alpha = [];

    /** @var array<int, array<int, float>> */
    private array $beta = [];

    /** @var array<int, array<int, float>> */
    private array $evidence = [];

    private function __construct(private readonly DeclaredHours $declared)
    {
        for ($day = 1; $day <= 7; $day++) {
            for ($slot = 0; $slot < Slots::COUNT; $slot++) {
                [$this->alpha[$day][$slot], $this->beta[$day][$slot]] = self::prior($declared->isOpenAt($day, Slots::middle($slot)));
                $this->evidence[$day][$slot] = 0.0;
            }
        }
    }

    /** @param iterable<Sighting> $sightings */
    public static function learn(DeclaredHours $declared, iterable $sightings = []): self
    {
        $model = new self($declared);
        foreach ($sightings as $sighting) {
            $model->add($sighting);
        }

        return $model;
    }

    public function declared(): DeclaredHours
    {
        return $this->declared;
    }

    /** P(open) = α / (α + β) for one slot. */
    public function slotProbability(int $weekday, int $slot): float
    {
        return $this->alpha[$weekday][$slot] / ($this->alpha[$weekday][$slot] + $this->beta[$weekday][$slot]);
    }

    /** P(open) at a minute of the day, with a low constant outside the modelled hours. */
    public function probability(int $weekday, int $minuteOfDay): float
    {
        $slot = Slots::index($minuteOfDay);

        return $slot === null ? self::OUTSIDE_HOURS_P : $this->slotProbability($weekday, $slot);
    }

    /** Effective number of observations behind a slot (neighbours count half). */
    public function confidence(int $weekday, int $slot): float
    {
        return $this->evidence[$weekday][$slot];
    }

    /** @return array{0: float, 1: float} */
    public function posterior(int $weekday, int $slot): array
    {
        return [$this->alpha[$weekday][$slot], $this->beta[$weekday][$slot]];
    }

    public function isReliablyOpen(int $weekday, int $slot): bool
    {
        return $this->slotProbability($weekday, $slot) >= self::RELIABLY_OPEN;
    }

    /**
     * The first reliably-open moment at or after the given time, looking up to a week ahead.
     *
     * @return array{weekday: int, minute: int}|null
     */
    public function nextReliablyOpen(int $weekday, int $minuteOfDay): ?array
    {
        for ($offset = 0; $offset <= 7; $offset++) {
            $day = ($weekday - 1 + $offset) % 7 + 1;
            $from = 0;
            if ($offset === 0) {
                if ($minuteOfDay >= Slots::LAST_MINUTE) {
                    continue;
                }
                $from = Slots::index(max($minuteOfDay, Slots::FIRST_MINUTE)) ?? 0;
            }
            for ($slot = $from; $slot < Slots::COUNT; $slot++) {
                if ($this->isReliablyOpen($day, $slot)) {
                    $minute = $offset === 0 ? max($minuteOfDay, Slots::start($slot)) : Slots::start($slot);

                    return ['weekday' => $day, 'minute' => $minute];
                }
            }
        }

        return null;
    }

    /** When the reliably-open stretch that contains this minute ends, or null if it isn't reliably open. */
    public function reliablyOpenUntil(int $weekday, int $minuteOfDay): ?int
    {
        $slot = Slots::index($minuteOfDay);
        if ($slot === null || ! $this->isReliablyOpen($weekday, $slot)) {
            return null;
        }
        while ($slot + 1 < Slots::COUNT && $this->isReliablyOpen($weekday, $slot + 1)) {
            $slot++;
        }

        return Slots::end($slot);
    }

    /** The end of the last reliably-open slot that finishes at or before this minute, the same day. */
    public function lastReliablyOpenBefore(int $weekday, int $minuteOfDay): ?int
    {
        for ($slot = Slots::COUNT - 1; $slot >= 0; $slot--) {
            if (Slots::end($slot) <= $minuteOfDay && $this->isReliablyOpen($weekday, $slot)) {
                return Slots::end($slot);
            }
        }

        return null;
    }

    /**
     * The grid for the API: per weekday, per slot, P(open) and the evidence behind it.
     *
     * @return list<array{weekday: int, p: list<float>, n: list<float>}>
     */
    public function toArray(): array
    {
        $rows = [];
        for ($day = 1; $day <= 7; $day++) {
            $rows[] = [
                'weekday' => $day,
                'p' => array_map(fn (int $slot) => round($this->slotProbability($day, $slot), 3), range(0, Slots::COUNT - 1)),
                'n' => array_map(fn (int $slot) => round($this->evidence[$day][$slot], 1), range(0, Slots::COUNT - 1)),
            ];
        }

        return $rows;
    }

    private function add(Sighting $sighting): void
    {
        $slot = Slots::index($sighting->minute);
        if ($slot === null || $sighting->weekday < 1 || $sighting->weekday > 7) {
            return;
        }
        $this->weigh($sighting->weekday, $slot, 1.0, $sighting->open);
        if ($slot > 0) {
            $this->weigh($sighting->weekday, $slot - 1, self::NEIGHBOUR_WEIGHT, $sighting->open);
        }
        if ($slot < Slots::COUNT - 1) {
            $this->weigh($sighting->weekday, $slot + 1, self::NEIGHBOUR_WEIGHT, $sighting->open);
        }
    }

    private function weigh(int $weekday, int $slot, float $weight, bool $open): void
    {
        if ($open) {
            $this->alpha[$weekday][$slot] += $weight;
        } else {
            $this->beta[$weekday][$slot] += $weight;
        }
        $this->evidence[$weekday][$slot] += $weight;
    }

    /** @return array{0: float, 1: float} */
    private static function prior(?bool $declaredOpen): array
    {
        return match ($declaredOpen) {
            true => [2.0, 1.0],
            false => [1.0, 2.0],
            null => [1.0, 1.0],
        };
    }
}
