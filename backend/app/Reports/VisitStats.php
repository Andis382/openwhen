<?php

namespace App\Reports;

use App\Models\Observation;
use App\Models\Trip;
use App\Models\TripStop;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Closed-visit rates and cash, counted per visit: a shop found shut at 08:40 and delivered at
 * 13:10 is two visits, one of them closed. Dates are trip dates.
 */
class VisitStats
{
    /** @return array{visits: int, closed: int, rate: ?float} */
    public function closedRate(CarbonInterface $from, CarbonInterface $to): array
    {
        $row = $this->visits($from, $to)
            ->selectRaw('count(*) as visits, sum(case when observations.is_open then 0 else 1 end) as closed')
            ->first();

        return $this->rate((int) $row->visits, (int) $row->closed);
    }

    /** @return list<array{date: string, visits: int, closed: int, rate: ?float}> */
    public function daily(CarbonInterface $from, CarbonInterface $to): array
    {
        $rows = $this->visits($from, $to)
            ->selectRaw('trips.date as day, count(*) as visits, sum(case when observations.is_open then 0 else 1 end) as closed')
            ->groupBy('trips.date')
            ->get()
            ->keyBy(fn ($r) => substr((string) $r->day, 0, 10));

        $days = [];
        for ($day = CarbonImmutable::instance($from)->startOfDay(); $day->lte($to); $day = $day->addDay()) {
            $row = $rows[$day->toDateString()] ?? null;
            $days[] = ['date' => $day->toDateString()] + $this->rate((int) ($row->visits ?? 0), (int) ($row->closed ?? 0));
        }

        return $days;
    }

    /** @return list<array{week: string, visits: int, closed: int, rate: ?float}> weeks starting on Monday */
    public function weekly(CarbonInterface $from, CarbonInterface $to): array
    {
        $weeks = [];
        foreach ($this->daily($from, $to) as $day) {
            $week = CarbonImmutable::parse($day['date'])->startOfWeek()->toDateString();
            $weeks[$week] ??= ['week' => $week, 'visits' => 0, 'closed' => 0];
            $weeks[$week]['visits'] += $day['visits'];
            $weeks[$week]['closed'] += $day['closed'];
        }

        return array_values(array_map(fn (array $w) => ['week' => $w['week']] + $this->rate($w['visits'], $w['closed']), $weeks));
    }

    /** @return list<array{id: ?int, name: ?string, visits: int, closed: int, rate: ?float}> */
    public function byDriver(CarbonInterface $from, CarbonInterface $to): array
    {
        return $this->grouped($this->visits($from, $to)->leftJoin('users', 'users.id', '=', 'trips.driver_id'), 'trips.driver_id', 'users.name');
    }

    /** @return list<array{id: ?int, name: ?string, visits: int, closed: int, rate: ?float}> */
    public function byRoute(CarbonInterface $from, CarbonInterface $to): array
    {
        return $this->grouped($this->visits($from, $to), 'trips.route_template_id', 'trips.name');
    }

    /** Shops found shut most often. @return list<array{id: int, name: string, town: string, visits: int, closed: int, rate: ?float}> */
    public function problemShops(CarbonInterface $from, CarbonInterface $to, int $limit = 6): array
    {
        return $this->visits($from, $to)
            ->join('shops', 'shops.id', '=', 'observations.shop_id')
            ->selectRaw('shops.id, shops.name, shops.town, count(*) as visits, sum(case when observations.is_open then 0 else 1 end) as closed')
            ->groupBy('shops.id', 'shops.name', 'shops.town')
            ->havingRaw('sum(case when observations.is_open then 0 else 1 end) > 0')
            ->orderByDesc('closed')
            ->orderBy('shops.name')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => ['id' => (int) $r->id, 'name' => $r->name, 'town' => $r->town] + $this->rate((int) $r->visits, (int) $r->closed))
            ->all();
    }

    /** Expected against collected, one row per trip that has started. */
    public function cash(CarbonInterface $from, CarbonInterface $to): array
    {
        return Trip::with('driver')
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->where('status', '!=', Trip::PLANNED)
            ->withCount(['stops as delivered_count' => fn ($q) => $q->where('outcome', TripStop::DELIVERED)])
            ->orderByDesc('date')
            ->orderBy('name')
            ->get()
            ->map(fn (Trip $t) => [
                'tripId' => $t->id,
                'date' => $t->date->toDateString(),
                'name' => $t->name,
                'status' => $t->status,
                'driver' => $t->driver?->name,
                'delivered' => $t->delivered_count,
                'expectedCents' => $t->cash_expected_cents,
                'collectedCents' => $t->cash_collected_cents,
                'differenceCents' => $t->cash_collected_cents - $t->cash_expected_cents,
            ])
            ->all();
    }

    /** Observations from drivers' visits on trips dated in the range, tenant-scoped through the model. */
    private function visits(CarbonInterface $from, CarbonInterface $to): Builder
    {
        return Observation::query()
            ->where('observations.source', Observation::FROM_VISIT)
            ->join('trip_stops', 'trip_stops.id', '=', 'observations.trip_stop_id')
            ->join('trips', 'trips.id', '=', 'trip_stops.trip_id')
            ->whereBetween('trips.date', [$from->toDateString(), $to->toDateString()])
            ->toBase();
    }

    private function grouped(Builder $query, string $key, string $label): array
    {
        return $query
            ->selectRaw("{$key} as id, {$label} as name, count(*) as visits, sum(case when observations.is_open then 0 else 1 end) as closed")
            ->groupBy(DB::raw($key), DB::raw($label))
            ->orderBy(DB::raw($label))
            ->get()
            ->map(fn ($r) => ['id' => $r->id === null ? null : (int) $r->id, 'name' => $r->name] + $this->rate((int) $r->visits, (int) $r->closed))
            ->all();
    }

    /** @return array{visits: int, closed: int, rate: ?float} */
    private function rate(int $visits, int $closed): array
    {
        return ['visits' => $visits, 'closed' => $closed, 'rate' => $visits ? round($closed / $visits, 4) : null];
    }
}
