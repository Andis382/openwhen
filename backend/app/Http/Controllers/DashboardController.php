<?php

namespace App\Http\Controllers;

use App\Hours\HoursComparison;
use App\Hours\OpenState;
use App\Hours\ShopHours;
use App\Hours\Slots;
use App\Models\Observation;
use App\Models\Shop;
use App\Models\Trip;
use App\Models\TripStop;
use App\Reports\VisitStats;
use App\Support\LocalTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/** The dispatcher's morning screen: today's vans, how often shutters are down, and who is open when. */
class DashboardController extends Controller
{
    public function __construct(
        private readonly ShopHours $hours,
        private readonly VisitStats $stats,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $clock = LocalTime::for($request->user()->organization);
        $today = $clock->today();
        $tomorrow = $today->addDay();
        $problemShops = $this->stats->problemShops($today->subDays(29), $today);
        $shops = Shop::whereIn('id', array_column($problemShops, 'id'))->get()->keyBy('id');
        $tomorrowTrips = Trip::whereDate('date', $tomorrow->toDateString())->get();

        return response()->json([
            'today' => $today->toDateString(),
            'trips' => $this->todaysTrips($today->toDateString()),
            'tomorrow' => [
                'date' => $tomorrow->toDateString(),
                'trips' => $tomorrowTrips->count(),
                'unpublished' => $tomorrowTrips->whereNull('published_at')->count(),
                'notOptimised' => $tomorrowTrips->whereNull('optimised_at')->count(),
            ],
            'closedRate' => [
                'week' => $this->stats->closedRate($today->subDays(6), $today),
                'previousWeek' => $this->stats->closedRate($today->subDays(13), $today->subDays(7)),
                'month' => $this->stats->closedRate($today->subDays(29), $today),
                'previousMonth' => $this->stats->closedRate($today->subDays(59), $today->subDays(30)),
            ],
            'daily' => $this->stats->daily($today->subDays(29), $today),
            'problemShops' => array_map(fn (array $row) => $row + [
                'rule' => isset($shops[$row['id']]) ? ($this->hours->rules($shops[$row['id']])[0] ?? null)?->toArray() : null,
            ], $problemShops),
            'hoursToFix' => $this->hoursToFix(),
        ]);
    }

    /** Shops whose declared hours the visits contradict, the most contradicted first. */
    private function hoursToFix(): array
    {
        $shops = Shop::where('active', true)->orderBy('name')->get();
        $this->hours->models($shops);
        $rows = [];
        foreach ($shops as $shop) {
            $days = array_values(array_filter($this->hours->comparison($shop), fn (array $d) => $d['status'] === HoursComparison::MISMATCH));
            if ($days) {
                $rows[] = [
                    'id' => $shop->id,
                    'name' => $shop->name,
                    'town' => $shop->town,
                    'days' => count($days),
                    'weekday' => $days[0]['weekday'],
                    'conflict' => $days[0]['conflicts'][0],
                ];
            }
        }
        usort($rows, fn (array $a, array $b) => [$b['days'], $a['name']] <=> [$a['days'], $b['name']]);

        return ['total' => count($rows), 'shops' => array_slice($rows, 0, 5)];
    }

    /**
     * Every active shop coloured by its chance of being open at one moment. Without a time it is
     * now, or 08:00 of the next working morning when now is outside 06:00–21:00.
     */
    public function map(Request $request): JsonResponse
    {
        $data = $request->validate([
            'weekday' => ['nullable', 'integer', 'between:1,7', 'required_with:time'],
            'time' => ['nullable', 'date_format:H:i', 'required_with:weekday'],
        ]);
        $organization = $request->user()->organization;
        $clock = LocalTime::for($organization);
        $now = $clock->now();
        [$weekday, $minute] = $clock->weekdayMinute($now);
        $isNow = true;
        if (isset($data['weekday'])) {
            [$weekday, $minute, $isNow] = [(int) $data['weekday'], (int) substr($data['time'], 0, 2) * 60 + (int) substr($data['time'], 3, 2), false];
        } elseif (Slots::index($minute) === null) {
            $weekday = $minute >= Slots::LAST_MINUTE ? $now->addDay()->dayOfWeekIso : $weekday;
            [$minute, $isNow] = [480, false];
        }

        $shops = Shop::where('active', true)->orderBy('name')->get();
        $models = $this->hours->models($shops);
        $depot = $organization->depot();

        return response()->json([
            'at' => ['weekday' => $weekday, 'time' => Slots::format($minute), 'isNow' => $isNow],
            'depot' => $depot ? ['name' => $organization->depot_name, 'lat' => $depot->lat, 'lng' => $depot->lng] : null,
            'shops' => $shops->map(function (Shop $shop) use ($models, $weekday, $minute) {
                $state = OpenState::at($models[$shop->id], $weekday, $minute);

                return [
                    'id' => $shop->id,
                    'name' => $shop->name,
                    'address' => $shop->address,
                    'town' => $shop->town,
                    'lat' => $shop->lat,
                    'lng' => $shop->lng,
                    'p' => $state['p'],
                    'state' => $state['state'],
                    'confidence' => $state['confidence'],
                    'next' => $state['next'],
                    'until' => $state['until'],
                ];
            }),
        ]);
    }

    private function todaysTrips(string $today): Collection
    {
        $trips = Trip::with('driver')->whereDate('date', $today)->orderBy('start_minute')->orderBy('name')->get();
        $stops = TripStop::with('shop:id,name')->whereIn('trip_id', $trips->pluck('id'))->orderBy('position')->get()->groupBy('trip_id');
        $closed = Observation::query()
            ->where('observations.is_open', false)
            ->join('trip_stops', 'trip_stops.id', '=', 'observations.trip_stop_id')
            ->whereIn('trip_stops.trip_id', $trips->pluck('id'))
            ->toBase()
            ->selectRaw('trip_stops.trip_id, count(*) as closed')
            ->groupBy('trip_stops.trip_id')
            ->pluck('closed', 'trip_id');

        return $trips->map(function (Trip $trip) use ($stops, $closed) {
            $mine = $stops[$trip->id] ?? collect();
            $next = $mine->first(fn (TripStop $s) => $s->outcome === null);

            return $trip->toApi() + [
                'stops' => $mine->count(),
                'done' => $mine->whereNotNull('outcome')->count(),
                'delivered' => $mine->where('outcome', TripStop::DELIVERED)->count(),
                'closed' => (int) ($closed[$trip->id] ?? 0),
                'amountDueCents' => (int) $mine->sum('amount_due_cents'),
                'nextStop' => $next ? ['name' => $next->shop->name, 'eta' => $next->outcomeApi()['plannedEta']] : null,
                'lastOutcomeAt' => $mine->max('outcome_at')?->toIso8601String(),
            ];
        })->values();
    }
}
