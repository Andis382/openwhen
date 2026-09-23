<?php

namespace App\Hours;

/**
 * Turns a learned model plus the sightings behind it into plain rules.
 *
 * Candidates come from the posterior of each weekday: where the first reliably-open slot starts,
 * where the last one ends, and the gaps (P < 0.5) between them. Days whose candidates lie within
 * half an hour of each other are reported together, with the more cautious boundary
 * ("Weekdays: closed 13:00–15:00"). A candidate only becomes a rule when the raw sightings inside
 * its window back it on every day it names: at least three in total, at most one in five open.
 */
final class RuleExtractor
{
    public const MIN_EVIDENCE = 3;

    public const MAX_OPEN_SHARE = 0.2;

    private const GAP_BELOW = 0.5;

    private const ORDER = [HoursRule::CLOSED_DAY, HoursRule::OPENS_AFTER, HoursRule::CLOSED_WINDOW, HoursRule::CLOSED_AFTER];

    /**
     * @param  iterable<Sighting>  $sightings
     * @return list<HoursRule>
     */
    public function extract(OpeningHoursModel $model, iterable $sightings): array
    {
        $byDay = array_fill(1, 7, []);
        foreach ($sightings as $s) {
            if ($s->weekday >= 1 && $s->weekday <= 7 && Slots::index($s->minute) !== null) {
                $byDay[$s->weekday][] = $s;
            }
        }

        $rules = [];
        $closedDays = [];
        $candidates = [];
        for ($day = 1; $day <= 7; $day++) {
            $sightingsOfDay = $byDay[$day];
            if (count($sightingsOfDay) >= self::MIN_EVIDENCE && $this->openShare($sightingsOfDay) <= self::MAX_OPEN_SHARE) {
                $closedDays[] = $day;

                continue;
            }
            foreach ($this->candidatesFor($model, $day) as [$type, $from, $to]) {
                $candidates[] = ['type' => $type, 'from' => $from, 'to' => $to, 'day' => $day];
            }
        }

        if ($closedDays) {
            $all = array_merge(...array_map(fn (int $d) => $byDay[$d], $closedDays));
            $rules[] = new HoursRule(HoursRule::CLOSED_DAY, $closedDays, null, null, $this->openCount($all), count($all));
        }

        foreach ($this->merge($candidates) as $candidate) {
            $rule = $this->backedRule($candidate, $byDay);
            if ($rule !== null) {
                $rules[] = $rule;
            }
        }

        usort($rules, fn (HoursRule $a, HoursRule $b) => [array_search($a->type, self::ORDER, true), $a->weekdays[0], $a->from]
            <=> [array_search($b->type, self::ORDER, true), $b->weekdays[0], $b->from]);

        return $rules;
    }

    /** @return list<array{0: string, 1: int, 2: ?int}> */
    private function candidatesFor(OpeningHoursModel $model, int $day): array
    {
        $open = array_values(array_filter(range(0, Slots::COUNT - 1), fn (int $slot) => $model->isReliablyOpen($day, $slot)));
        if (! $open) {
            return [];
        }
        $first = $open[0];
        $last = $open[count($open) - 1];

        $found = [];
        if ($first > 0) {
            $found[] = [HoursRule::OPENS_AFTER, Slots::start($first), null];
        }
        if ($last < Slots::COUNT - 1) {
            $found[] = [HoursRule::CLOSED_AFTER, Slots::end($last), null];
        }
        $slot = $first;
        while ($slot <= $last) {
            if ($model->slotProbability($day, $slot) >= self::GAP_BELOW) {
                $slot++;

                continue;
            }
            $gapStart = $slot;
            while ($slot <= $last && $model->slotProbability($day, $slot) < self::GAP_BELOW) {
                $slot++;
            }
            $found[] = [HoursRule::CLOSED_WINDOW, Slots::start($gapStart), Slots::start($slot)];
        }

        return $found;
    }

    /**
     * Groups the same kind of candidate across weekdays when its boundaries are at most one slot
     * apart. The group keeps the weakest claim: the earliest "opens after", the latest "closed
     * after", the overlap of the closed windows.
     *
     * @param  list<array{type: string, from: int, to: ?int, day: int}>  $candidates
     * @return list<array{type: string, from: int, to: ?int, days: list<int>}>
     */
    private function merge(array $candidates): array
    {
        usort($candidates, fn (array $a, array $b) => [$a['type'], $a['from'], $a['to'], $a['day']] <=> [$b['type'], $b['from'], $b['to'], $b['day']]);
        $groups = [];
        foreach ($candidates as $c) {
            foreach ($groups as &$group) {
                if ($group['type'] !== $c['type'] || in_array($c['day'], $group['days'], true)
                    || abs($group['anchor'][0] - $c['from']) > Slots::LENGTH || abs(($group['anchor'][1] ?? 0) - ($c['to'] ?? 0)) > Slots::LENGTH) {
                    continue;
                }
                [$from, $to] = match ($c['type']) {
                    HoursRule::OPENS_AFTER => [min($group['from'], $c['from']), null],
                    HoursRule::CLOSED_AFTER => [max($group['from'], $c['from']), null],
                    default => [max($group['from'], $c['from']), min($group['to'], $c['to'])],
                };
                if ($to !== null && $to <= $from) {
                    continue;
                }
                $group['from'] = $from;
                $group['to'] = $to;
                $group['days'][] = $c['day'];

                continue 2;
            }
            unset($group);
            $groups[] = ['type' => $c['type'], 'from' => $c['from'], 'to' => $c['to'], 'days' => [$c['day']], 'anchor' => [$c['from'], $c['to']]];
        }
        unset($group);

        return array_map(function (array $g) {
            sort($g['days']);
            unset($g['anchor']);

            return $g;
        }, $groups);
    }

    /**
     * Keeps the days whose own sightings in the window agree, then asks for enough of them in total.
     *
     * @param  array{type: string, from: int, to: ?int, days: list<int>}  $candidate
     * @param  array<int, list<Sighting>>  $byDay
     */
    private function backedRule(array $candidate, array $byDay): ?HoursRule
    {
        [$windowStart, $windowEnd] = match ($candidate['type']) {
            HoursRule::OPENS_AFTER => [Slots::FIRST_MINUTE, $candidate['from']],
            HoursRule::CLOSED_AFTER => [$candidate['from'], Slots::LAST_MINUTE],
            default => [$candidate['from'], $candidate['to']],
        };

        $days = [];
        $evidence = [];
        foreach ($candidate['days'] as $day) {
            $inWindow = array_values(array_filter($byDay[$day], fn (Sighting $s) => $s->minute >= $windowStart && $s->minute < $windowEnd));
            if ($inWindow && $this->openShare($inWindow) <= self::MAX_OPEN_SHARE) {
                $days[] = $day;
                array_push($evidence, ...$inWindow);
            }
        }
        if (count($evidence) < self::MIN_EVIDENCE) {
            return null;
        }

        return new HoursRule($candidate['type'], $days, $candidate['from'], $candidate['to'], $this->openCount($evidence), count($evidence));
    }

    /** @param list<Sighting> $sightings */
    private function openCount(array $sightings): int
    {
        return count(array_filter($sightings, fn (Sighting $s) => $s->open));
    }

    /** @param list<Sighting> $sightings */
    private function openShare(array $sightings): float
    {
        return $sightings ? $this->openCount($sightings) / count($sightings) : 0.0;
    }
}
