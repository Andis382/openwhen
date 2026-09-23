<?php

namespace App\Routing;

/** One order of stops with its arrival times and what it costs. */
final class RoutePlan
{
    /**
     * @param  list<array{id: int, eta: int, pOpen: float, kmFromPrevious: float}>  $visits  in driving order
     */
    public function __construct(
        public readonly array $visits,
        public readonly float $km,
        public readonly float $travelMinutes,
        public readonly float $expectedClosed,
        public readonly int $finishMinute,
        public readonly float $cost,
    ) {}

    /** @return list<int> */
    public function order(): array
    {
        return array_map(fn (array $v) => $v['id'], $this->visits);
    }

    /** @return array{km: float, travelMinutes: int, expectedClosed: float, finishMinute: int} */
    public function summary(): array
    {
        return [
            'km' => round($this->km, 1),
            'travelMinutes' => (int) round($this->travelMinutes),
            'expectedClosed' => round($this->expectedClosed, 2),
            'finishMinute' => $this->finishMinute,
        ];
    }
}
