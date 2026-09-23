<?php

namespace App\Routing;

final class GeoPoint
{
    private const EARTH_RADIUS_KM = 6371.0088;

    public function __construct(
        public readonly float $lat,
        public readonly float $lng,
    ) {}

    /** Great-circle distance in kilometres. */
    public function kmTo(GeoPoint $other): float
    {
        $dLat = deg2rad($other->lat - $this->lat);
        $dLng = deg2rad($other->lng - $this->lng);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($this->lat)) * cos(deg2rad($other->lat)) * sin($dLng / 2) ** 2;

        return 2 * self::EARTH_RADIUS_KM * asin(min(1.0, sqrt($a)));
    }

    public function metresTo(GeoPoint $other): int
    {
        return (int) round($this->kmTo($other) * 1000);
    }
}
