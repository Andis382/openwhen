<?php

namespace App\Hours;

/**
 * Declared hours next to what the visits show, one weekday at a time.
 *
 * Observed hours are the stretches of reliably-open slots. A day disagrees with its declaration
 * on the same evidence the rules use: a learned closed window that the shop claims to be open in,
 * or a declared-closed stretch where at least three visits found it open at least four times in five.
 */
final class HoursComparison
{
    public const MATCH = 'match';

    public const MISMATCH = 'mismatch';

    public const UNDECLARED = 'undeclared';

    public const TOO_FEW = 'too_few';

    private const MIN_SIGHTINGS = 3;

    private const MIN_OPEN_SHARE = 0.8;

    /**
     * @param  iterable<Sighting>  $sightings
     * @param  list<HoursRule>  $rules  as extracted from the same model and sightings
     * @return list<array{weekday: int, declared: list<array{0: string, 1: string}>|null, observed: list<array{0: string, 1: string}>, status: string, conflicts: list<array{from: string, to: string, declaredOpen: bool, open: int, total: int, days: list<int>}>, visits: int}>
     */
    public function compare(OpeningHoursModel $model, iterable $sightings, array $rules): array
    {
        $byDay = array_fill(1, 7, []);
        foreach ($sightings as $s) {
            if ($s->weekday >= 1 && $s->weekday <= 7 && Slots::index($s->minute) !== null) {
                $byDay[$s->weekday][] = $s;
            }
        }
        $declared = $model->declared();

        $days = [];
        for ($day = 1; $day <= 7; $day++) {
            $conflicts = array_merge(
                $this->shutWhileDeclaredOpen($declared, $day, $rules),
                $this->openWhileDeclaredShut($declared, $day, $byDay[$day]),
            );
            usort($conflicts, fn (array $a, array $b) => $a['from'] <=> $b['from']);
            $visits = count($byDay[$day]);
            $days[] = [
                'weekday' => $day,
                'declared' => $declared->toArray()[(string) $day],
                'observed' => $visits < self::MIN_SIGHTINGS ? [] : $this->observedIntervals($model, $day),
                'status' => match (true) {
                    $conflicts !== [] => self::MISMATCH,
                    $visits < self::MIN_SIGHTINGS => self::TOO_FEW,
                    ! $declared->isKnown($day) => self::UNDECLARED,
                    default => self::MATCH,
                },
                'conflicts' => array_map(fn (array $c) => ['from' => Slots::format($c['from']), 'to' => Slots::format($c['to'])] + $c, $conflicts),
                'visits' => $visits,
            ];
        }

        return $days;
    }

    /**
     * Learned closed windows that overlap the declared opening hours.
     *
     * @param  list<HoursRule>  $rules
     */
    private function shutWhileDeclaredOpen(DeclaredHours $declared, int $day, array $rules): array
    {
        $conflicts = [];
        foreach ($rules as $rule) {
            if (! $rule->appliesTo($day)) {
                continue;
            }
            [$shutFrom, $shutTo] = match ($rule->type) {
                HoursRule::OPENS_AFTER => [Slots::FIRST_MINUTE, $rule->from],
                HoursRule::CLOSED_AFTER => [$rule->from, Slots::LAST_MINUTE],
                HoursRule::CLOSED_WINDOW => [$rule->from, $rule->to],
                default => [Slots::FIRST_MINUTE, Slots::LAST_MINUTE],
            };
            foreach ($declared->intervals($day) ?? [] as [$open, $close]) {
                $from = max($open, $shutFrom);
                $to = min($close, $shutTo);
                if ($from < $to) {
                    // The rule's own evidence, gathered over all the days it covers.
                    $conflicts[] = ['from' => $from, 'to' => $to, 'declaredOpen' => true, 'open' => $rule->open, 'total' => $rule->total, 'days' => $rule->weekdays];
                }
            }
        }

        return $conflicts;
    }

    /**
     * Declared-closed stretches of the modelled day where visits kept finding the shop open.
     *
     * @param  list<Sighting>  $sightings
     */
    private function openWhileDeclaredShut(DeclaredHours $declared, int $day, array $sightings): array
    {
        $intervals = $declared->intervals($day);
        if ($intervals === null) {
            return [];
        }
        $gaps = [];
        $cursor = Slots::FIRST_MINUTE;
        foreach ($intervals as [$open, $close]) {
            if ($open > $cursor) {
                $gaps[] = [$cursor, min($open, Slots::LAST_MINUTE)];
            }
            $cursor = max($cursor, $close);
        }
        if ($cursor < Slots::LAST_MINUTE) {
            $gaps[] = [$cursor, Slots::LAST_MINUTE];
        }

        $conflicts = [];
        foreach ($gaps as [$from, $to]) {
            $evidence = $this->evidence($sightings, $from, $to);
            if ($from < $to && $evidence['total'] >= self::MIN_SIGHTINGS && $evidence['open'] >= self::MIN_OPEN_SHARE * $evidence['total']) {
                $conflicts[] = ['from' => $from, 'to' => $to, 'declaredOpen' => false] + $evidence + ['days' => [$day]];
            }
        }

        return $conflicts;
    }

    /**
     * @param  list<Sighting>  $sightings
     * @return array{open: int, total: int}
     */
    private function evidence(array $sightings, int $from, int $to): array
    {
        $inside = array_filter($sightings, fn (Sighting $s) => $s->minute >= $from && $s->minute < $to);

        return ['open' => count(array_filter($inside, fn (Sighting $s) => $s->open)), 'total' => count($inside)];
    }

    /** @return list<array{0: string, 1: string}> */
    private function observedIntervals(OpeningHoursModel $model, int $day): array
    {
        $intervals = [];
        $slot = 0;
        while ($slot < Slots::COUNT) {
            if (! $model->isReliablyOpen($day, $slot)) {
                $slot++;

                continue;
            }
            $start = $slot;
            while ($slot < Slots::COUNT && $model->isReliablyOpen($day, $slot)) {
                $slot++;
            }
            $intervals[] = [Slots::format(Slots::start($start)), Slots::format(Slots::start($slot))];
        }

        return $intervals;
    }
}
