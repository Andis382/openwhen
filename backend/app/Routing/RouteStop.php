<?php

namespace App\Routing;

use App\Hours\OpeningHoursModel;
use App\Hours\Slots;

/** A shop to visit, with its chance of being open in each half-hour slot of the trip's weekday. */
final class RouteStop
{
    /** @param list<float> $slotProbabilities one P(open) per slot of Slots, 06:00–21:00 */
    public function __construct(
        public readonly int $id,
        public readonly GeoPoint $point,
        public readonly array $slotProbabilities,
    ) {}

    public static function fromModel(int $id, GeoPoint $point, OpeningHoursModel $model, int $weekday): self
    {
        return new self($id, $point, array_map(fn (int $slot) => $model->slotProbability($weekday, $slot), range(0, Slots::COUNT - 1)));
    }

    public function pOpen(float $minuteOfDay): float
    {
        $slot = Slots::index((int) floor($minuteOfDay));

        return $slot === null ? OpeningHoursModel::OUTSIDE_HOURS_P : $this->slotProbabilities[$slot];
    }
}
