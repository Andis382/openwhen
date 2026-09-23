<?php

namespace Database\Seeders\Demo;

use App\Models\Shop;
use App\Models\TripStop;
use App\Routing\GeoPoint;
use App\Routing\RouteOptimizer;
use App\Support\LocalTime;
use Carbon\CarbonImmutable;

/**
 * Drives a demo van along a list of shops: arrival times from real distances, outcomes from the
 * shops' hidden true hours plus the everyday noise (an owner at the bank, a refused crate, a
 * shopkeeper who popped out). A shop found shut is sometimes tried again at the end of the round.
 */
final class VisitSimulator
{
    private const REVISIT_PERCENT = 35;

    private const CLOSED_NOTES = ['Qepenat poshtë.', 'Mbyllur, nuk u përgjigj në telefon.', 'Mbyllur, fqinji tha që vjen më vonë.'];

    /** @param array<int, array{pattern: string, absent: bool}> $truth keyed by shop id */
    public function __construct(
        private readonly GeoPoint $depot,
        private readonly LocalTime $clock,
        private readonly array $truth,
    ) {}

    /**
     * @param  list<Shop>  $shops  in driving order
     * @return array{stops: list<array{shop: Shop, due: int, visits: list<array{at: CarbonImmutable, outcome: string}>, collected: ?int, note: ?string, gps: ?array{lat: float, lng: float, accuracy: int}}>, startedAt: CarbonImmutable, finishedAt: ?CarbonImmutable}
     */
    public function drive(array $shops, CarbonImmutable $date, int $startMinute, ?CarbonImmutable $until = null): array
    {
        $weekday = $date->dayOfWeekIso;
        $clock = (float) $startMinute;
        $here = $this->depot;
        $stops = [];
        $retry = [];
        $stopped = false;

        foreach ($shops as $shop) {
            $stop = ['shop' => $shop, 'due' => $this->due($shop), 'visits' => [], 'collected' => null, 'note' => null, 'gps' => null];
            $arrival = $clock + $this->travel($here, $shop->point());
            if ($stopped || ($until !== null && $this->at($date, $arrival)->greaterThan($until))) {
                $stopped = true;
                $stops[] = $stop;

                continue;
            }
            $outcome = $this->outcome($shop, $weekday, (int) $arrival);
            $stop['visits'][] = ['at' => $this->at($date, $arrival), 'outcome' => $outcome];
            $stops[] = $stop;
            if ($outcome === TripStop::CLOSED && mt_rand(1, 100) <= self::REVISIT_PERCENT) {
                $retry[] = count($stops) - 1;
            }
            $clock = $arrival + mt_rand(4, 9);
            $here = $shop->point();
        }

        foreach ($stopped ? [] : $retry as $index) {
            $shop = $stops[$index]['shop'];
            $arrival = $clock + $this->travel($here, $shop->point());
            if ($until !== null && $this->at($date, $arrival)->greaterThan($until)) {
                break;
            }
            $stops[$index]['visits'][] = ['at' => $this->at($date, $arrival), 'outcome' => $this->outcome($shop, $weekday, (int) $arrival)];
            $clock = $arrival + mt_rand(4, 9);
            $here = $shop->point();
        }

        foreach ($stops as &$stop) {
            if ($stop['visits']) {
                $this->finishStop($stop, $weekday);
            }
        }
        unset($stop);

        $home = $clock + $this->travel($here, $this->depot);
        $finished = $stopped || ($until !== null && $this->at($date, $home)->greaterThan($until)) ? null : $this->at($date, $home);

        return ['stops' => $stops, 'startedAt' => $this->at($date, $startMinute), 'finishedAt' => $finished];
    }

    private function outcome(Shop $shop, int $weekday, int $minute): string
    {
        $truth = $this->truth[$shop->id];
        if (! TrueHours::isOpen($truth['pattern'], $weekday, $minute)) {
            return TripStop::CLOSED;
        }
        if ($truth['absent'] && in_array($weekday, [1, 4], true) && $minute >= 480 && $minute < 600 && mt_rand(1, 100) <= 60) {
            return TripStop::OWNER_ABSENT;
        }
        $roll = mt_rand(1, 100);

        return match (true) {
            $roll <= 2 => TripStop::REFUSED,
            $roll <= 5 => TripStop::CLOSED,
            default => TripStop::DELIVERED,
        };
    }

    /** Outcome details for the last visit: cash, a note the driver might type, the phone's position. */
    private function finishStop(array &$stop, int $weekday): void
    {
        $last = $stop['visits'][count($stop['visits']) - 1];
        $shop = $stop['shop'];
        $minute = $last['at']->hour * 60 + $last['at']->minute;
        $pattern = $this->truth[$shop->id]['pattern'];

        if ($last['outcome'] === TripStop::DELIVERED) {
            $roll = mt_rand(1, 100);
            $stop['collected'] = match (true) {
                $roll <= 6 => (int) (round($stop['due'] * mt_rand(50, 90) / 100 / 10000) * 10000),
                $roll <= 9 => (int) (round($stop['due'] * mt_rand(110, 130) / 100 / 10000) * 10000),
                default => $stop['due'],
            };
            if ($stop['collected'] < $stop['due']) {
                $stop['note'] = 'Pjesën tjetër e paguan javën tjetër.';
            } elseif ($stop['collected'] > $stop['due']) {
                $stop['note'] = 'Pagoi edhe borxhin e javës së kaluar.';
            }
        } elseif ($last['outcome'] === TripStop::CLOSED && mt_rand(1, 100) <= 55) {
            $stop['note'] = match (true) {
                $pattern === TrueHours::LUNCH && $minute >= 780 && $minute < 900 => 'Mbyllur për drekë.',
                $pattern === TrueHours::BANK_MORNINGS && in_array($weekday, [2, 4], true) && $minute < 630 => 'Pronari në bankë, sipas fqinjit.',
                default => self::CLOSED_NOTES[mt_rand(0, count(self::CLOSED_NOTES) - 1)],
            };
        } elseif ($last['outcome'] === TripStop::REFUSED) {
            $stop['note'] = mt_rand(0, 1) ? 'Ktheu kosin: afat i shkurtër.' : 'Nuk e donte porosinë sot.';
        } elseif ($last['outcome'] === TripStop::OWNER_ABSENT) {
            $stop['note'] = 'Pronari mungon, punonjësi nuk paguan dot.';
        }

        $stop['gps'] = [
            'lat' => round($shop->lat + mt_rand(-25, 25) / 100000, 6),
            'lng' => round($shop->lng + mt_rand(-25, 25) / 100000, 6),
            'accuracy' => mt_rand(4, 28),
        ];
    }

    /** Minutes of driving with a little traffic and parking. */
    private function travel(GeoPoint $from, GeoPoint $to): float
    {
        return RouteOptimizer::minutesForKm($from->kmTo($to)) + mt_rand(0, 30) / 10;
    }

    /** Standing orders vary a little from day to day; whole lek. */
    private function due(Shop $shop): int
    {
        return (int) (round($shop->order_value_cents * mt_rand(85, 115) / 100 / 10000) * 10000);
    }

    private function at(CarbonImmutable $date, float $minute): CarbonImmutable
    {
        return $this->clock->at($date, 0)->addSeconds((int) round($minute * 60));
    }
}
