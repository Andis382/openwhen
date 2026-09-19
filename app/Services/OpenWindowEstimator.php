<?php

namespace App\Services;

use App\Models\Shop;
use App\Models\Visit;
use Illuminate\Support\Collection;

/**
 * When is this shop actually open?
 *
 * Nobody knows. The owner set his hours on Google once in 2019 and then
 * started closing for lunch, or for market day, or for Friday prayer, or
 * because he is at the bank. Route planners take opening hours as an input and
 * never learn them. "Popular times" measures footfall and cannot tell you the
 * shutter was down.
 *
 * The only people who know are the drivers who call weekly, and they know it
 * in their heads. This turns their taps into a number.
 *
 * The arithmetic is deliberately dull: per weekday, per hour, what fraction of
 * visits found the shutter up, with Laplace smoothing so that one unlucky
 * Monday at nine does not become a rule. Three thresholds keep it honest:
 *
 *   an hour is only read when it has been visited at least twice;
 *   a weekday says nothing at all under four visits;
 *   a rule like "never before ten on Mondays" needs three observations in
 *   that stretch and near-total agreement.
 *
 * Everything this product sells is downstream of those three numbers, so they
 * live here as constants and every one of them is pinned in a test.
 */
class OpenWindowEstimator
{
    /** Below this many visits in an hour bucket, the hour is unknown. */
    public const MIN_PER_HOUR = 2;

    /** Below this many visits on a weekday, the whole day is unknown. */
    public const MIN_PER_WEEKDAY = 4;

    /** An hour counts as open above this smoothed probability. */
    public const OPEN_AT = 0.6;

    /** An hour counts as reliably shut below this one. */
    public const SHUT_AT = 0.25;

    /** Evidence needed before a "never before ten" style rule is stated. */
    public const RULE_EVIDENCE = 3;

    /** Laplace prior. One imaginary open and one imaginary closed visit. */
    public const PRIOR = 1.0;

    /** The trading hours worth reasoning about at all. */
    public const FIRST_HOUR = 5;
    public const LAST_HOUR = 22;

    /**
     * Everything known about one shop on one weekday.
     *
     * @return array{
     *     known: bool, visits: int,
     *     hours: array<int, array{n:int, open:int, p:float, known:bool}>,
     *     open_from: ?int, open_to: ?int, best_hour: ?int, rules: array<int, array{key:string, hour:int}>
     * }
     */
    public function profile(Shop $shop, int $weekday, ?Collection $visits = null): array
    {
        $visits ??= $shop->visits()->where('weekday', $weekday)->get();

        $hours = [];
        for ($hour = self::FIRST_HOUR; $hour <= self::LAST_HOUR; $hour++) {
            $hours[$hour] = ['n' => 0, 'open' => 0, 'p' => 0.5, 'known' => false];
        }

        foreach ($visits as $visit) {
            $hour = (int) $visit->hour;
            if (! isset($hours[$hour])) {
                continue;
            }

            $hours[$hour]['n']++;
            $hours[$hour]['open'] += $visit->foundOpen() ? 1 : 0;
        }

        foreach ($hours as $hour => $bucket) {
            $hours[$hour]['p'] = $this->smoothed($bucket['open'], $bucket['n']);
            $hours[$hour]['known'] = $bucket['n'] >= self::MIN_PER_HOUR;
        }

        $total = $visits->count();
        $known = $total >= self::MIN_PER_WEEKDAY;

        $openHours = collect($hours)
            ->filter(fn (array $bucket) => $bucket['known'] && $bucket['p'] >= self::OPEN_AT)
            ->keys();

        $best = collect($hours)
            ->filter(fn (array $bucket) => $bucket['known'])
            ->sortByDesc('p')
            ->keys()
            ->first();

        return [
            'known' => $known,
            'visits' => $total,
            'hours' => $hours,
            'open_from' => $known ? $openHours->min() : null,
            'open_to' => $known ? $openHours->max() : null,
            'best_hour' => $known ? $best : null,
            'rules' => $known ? $this->rules($hours) : [],
        ];
    }

    /**
     * The one hour to aim for. Null when the shop has not been seen enough.
     *
     * The middle of the reliably-open stretch rather than its first hour: the
     * edges of an opening window are exactly where a shop is most likely to be
     * five minutes the wrong side of it.
     */
    public function bestHour(Shop $shop, int $weekday, ?Collection $visits = null): ?int
    {
        $profile = $this->profile($shop, $weekday, $visits);

        if (! $profile['known'] || $profile['open_from'] === null) {
            return null;
        }

        return (int) round(($profile['open_from'] + $profile['open_to']) / 2);
    }

    /**
     * Statements strong enough to put in front of a person.
     *
     * "Never before ten on Mondays" is a useful sentence and a damaging one if
     * it is wrong, so it takes three visits in that stretch and near-total
     * agreement before it is said at all.
     *
     * @return array<int, array{key:string, hour:int}>
     */
    private function rules(array $hours): array
    {
        $rules = [];

        // A shut morning: every read hour before the first open one agrees.
        $firstOpen = collect($hours)->filter(fn ($b) => $b['known'] && $b['p'] >= self::OPEN_AT)->keys()->min();
        if ($firstOpen !== null) {
            $before = collect($hours)->filter(fn ($b, $h) => $h < $firstOpen && $b['n'] > 0);
            if ($before->sum('n') >= self::RULE_EVIDENCE && $before->every(fn ($b) => $b['p'] <= self::SHUT_AT)) {
                $rules[] = ['key' => 'not_before', 'hour' => $firstOpen];
            }
        }

        // A shut afternoon, on the same evidence.
        $lastOpen = collect($hours)->filter(fn ($b) => $b['known'] && $b['p'] >= self::OPEN_AT)->keys()->max();
        if ($lastOpen !== null) {
            $after = collect($hours)->filter(fn ($b, $h) => $h > $lastOpen && $b['n'] > 0);
            if ($after->sum('n') >= self::RULE_EVIDENCE && $after->every(fn ($b) => $b['p'] <= self::SHUT_AT)) {
                $rules[] = ['key' => 'not_after', 'hour' => $lastOpen + 1];
            }
        }

        // A hole in the middle: the lunch closure nobody ever wrote down, and
        // the single most expensive thing for a driver not to know.
        if ($firstOpen !== null && $lastOpen !== null) {
            foreach ($hours as $hour => $bucket) {
                if ($hour > $firstOpen && $hour < $lastOpen
                    && $bucket['n'] >= self::RULE_EVIDENCE && $bucket['p'] <= self::SHUT_AT) {
                    $rules[] = ['key' => 'shut_around', 'hour' => $hour];
                }
            }
        }

        return $rules;
    }

    /**
     * (open + 1) / (n + 2).
     *
     * With nothing seen this is 0.5 — genuinely unknown, not "probably open".
     * One closed visit gives 0.33 rather than 0, which is the difference
     * between a suspicion and a verdict.
     */
    private function smoothed(int $open, int $n): float
    {
        return round(($open + self::PRIOR) / ($n + 2 * self::PRIOR), 4);
    }

    /**
     * How much of a driver's day is being spent on shutters.
     *
     * @return array{visits:int, wasted:int, rate:float}
     */
    public function wasted(Collection $visits): array
    {
        $total = $visits->count();
        $wasted = $visits->filter(fn (Visit $visit) => $visit->wasWasted())->count();

        return [
            'visits' => $total,
            'wasted' => $wasted,
            'rate' => $total > 0 ? round($wasted / $total * 100, 1) : 0.0,
        ];
    }
}
