<?php

namespace App\Hours;

/** "Open now, until about 13:00" / "Shut now, opens about 10:00": a shop's state at one moment. */
final class OpenState
{
    public const OPEN = 'open';

    public const UNSURE = 'unsure';

    public const CLOSED = 'closed';

    /** @return array{p: float, state: string, until: ?string, next: ?array{weekday: int, time: string}, confidence: float} */
    public static function at(OpeningHoursModel $model, int $weekday, int $minuteOfDay): array
    {
        $p = $model->probability($weekday, $minuteOfDay);
        $state = match (true) {
            $p >= OpeningHoursModel::RELIABLY_OPEN => self::OPEN,
            $p <= OpeningHoursModel::RELIABLY_CLOSED => self::CLOSED,
            default => self::UNSURE,
        };
        $until = $state === self::OPEN ? $model->reliablyOpenUntil($weekday, $minuteOfDay) : null;
        $next = $state === self::OPEN ? null : $model->nextReliablyOpen($weekday, $minuteOfDay);
        $slot = Slots::index($minuteOfDay);

        return [
            'p' => round($p, 3),
            'state' => $state,
            'until' => $until === null ? null : Slots::format($until),
            'next' => $next === null ? null : ['weekday' => $next['weekday'], 'time' => Slots::format($next['minute'])],
            'confidence' => $slot === null ? 0.0 : round($model->confidence($weekday, $slot), 1),
        ];
    }

    /**
     * Why a planned arrival looks bad, or null when the shop is more likely open than not.
     *
     * @return array{type: string, eta: string, at: ?string, p: float}|null
     */
    public static function arrivalWarning(OpeningHoursModel $model, int $weekday, int $etaMinute): ?array
    {
        $p = $model->probability($weekday, $etaMinute);
        if ($p >= 0.5) {
            return null;
        }
        $warning = ['type' => 'unlikely', 'eta' => Slots::format($etaMinute), 'at' => null, 'p' => round($p, 3)];
        $next = $model->nextReliablyOpen($weekday, $etaMinute);
        if ($next !== null && $next['weekday'] === $weekday) {
            return ['type' => 'opens_later', 'at' => Slots::format($next['minute'])] + $warning;
        }
        $last = $model->lastReliablyOpenBefore($weekday, $etaMinute);
        if ($last !== null) {
            return ['type' => 'closed_since', 'at' => Slots::format($last)] + $warning;
        }

        return $warning;
    }
}
