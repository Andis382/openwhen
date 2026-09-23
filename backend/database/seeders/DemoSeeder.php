<?php

namespace Database\Seeders;

use App\Hours\DeclaredHours;
use App\Hours\OpeningHoursModel;
use App\Hours\Sighting;
use App\Models\Observation;
use App\Models\Organization;
use App\Models\RouteTemplate;
use App\Models\RouteTemplateStop;
use App\Models\Shop;
use App\Models\Trip;
use App\Models\TripStop;
use App\Models\User;
use App\Routing\GeoPoint;
use App\Routing\RouteOptimizer;
use App\Routing\RouteStop;
use App\Support\LocalTime;
use App\Support\Tenant;
use App\Trips\TripGenerator;
use App\Trips\TripPlanner;
use Carbon\CarbonImmutable;
use Database\Seeders\Demo\ShopCatalog;
use Database\Seeders\Demo\TrueHours;
use Database\Seeders\Demo\VisitSimulator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Qumështorja Dajti, a dairy distributor in Tirana with three vans: 75 shops, three rounds and
 * eight weeks of deliveries drawn from each shop's hidden real hours. For the first six weeks the
 * drivers took the rounds in whatever order suited them; for the last two the dispatcher has
 * been publishing optimised orders, so the closed-visit rate drops. Tomorrow's trips exist but
 * are not optimised yet.
 */
class DemoSeeder extends Seeder
{
    private const HISTORY_DAYS = 56;

    private const OPTIMISED_DAYS = 14;

    private const ROUNDS = [
        'V' => ['name' => 'Veri — Laprakë · Stacioni', 'weekdays' => [1, 2, 3, 4, 5, 6], 'start' => 420, 'driver' => 0],
        'Q' => ['name' => 'Qendër — Kavaja · Blloku', 'weekdays' => [1, 2, 3, 4, 5, 6, 7], 'start' => 450, 'driver' => 1],
        'L' => ['name' => 'Lindje — Elbasani · Porcelani', 'weekdays' => [1, 2, 3, 4, 5, 6], 'start' => 660, 'driver' => 2],
    ];

    /** @var array<int, list<Sighting>> what the vans have seen so far, per shop */
    private array $seen = [];

    public function run(): void
    {
        if (User::where('email', config('product.demo_email'))->exists()) {
            return;
        }
        mt_srand(20260923);

        $org = Organization::create([
            'name' => 'Qumështorja Dajti',
            'phone' => '355692045118',
            'locale' => 'sq',
            'timezone' => 'Europe/Tirane',
            'currency' => 'ALL',
            'depot_name' => 'Depoja, Rruga e Durrësit 212',
            'depot_lat' => 41.3417,
            'depot_lng' => 19.7934,
        ]);
        $people = $this->people($org);

        Tenant::run($org->id, function () use ($org, $people) {
            $clock = LocalTime::for($org);
            [$shops, $truth] = $this->shops();
            $templates = $this->templates($shops, $people['drivers'], $org->depot());
            $simulator = new VisitSimulator($org->depot(), $clock, $truth);

            $this->history($templates, $people['drivers'], $simulator, $clock);
            $this->today($templates, $simulator, $clock, $people['owner']);
            app(TripGenerator::class)->generate($clock->today()->addDay());
        });

        $this->command?->info('Demo data ready. Sign in with '.config('product.demo_email').' / '.config('product.demo_password')
            .' (drivers: driver1@openwhen.test … driver3@openwhen.test, dispatcher@openwhen.test)');
    }

    /** @return array{owner: User, drivers: list<User>} */
    private function people(Organization $org): array
    {
        $make = fn (string $name, string $email, string $role) => User::create([
            'organization_id' => $org->id,
            'name' => $name,
            'email' => $email,
            'password' => config('product.demo_password'),
            'role' => $role,
            'locale' => 'sq',
        ]);
        $owner = $make('Blerina Gjoka', config('product.demo_email'), User::OWNER);
        $make('Dritan Leka', 'dispatcher@openwhen.test', User::DISPATCHER);

        return [
            'owner' => $owner,
            'drivers' => [
                $make('Ervin Mema', 'driver1@openwhen.test', User::DRIVER),
                $make('Klodian Duka', 'driver2@openwhen.test', User::DRIVER),
                $make('Sokol Brahimi', 'driver3@openwhen.test', User::DRIVER),
            ],
        ];
    }

    /** @return array{0: Collection<string, Collection<int, Shop>>, 1: array<int, array{pattern: string, absent: bool}>} */
    private function shops(): array
    {
        $byRound = collect();
        $truth = [];
        foreach (ShopCatalog::SHOPS as $i => [$name, $street, $round, $town, $pattern, $declared, $contact, $absent]) {
            [$latMin, $latMax, $lngMin, $lngMax] = ShopCatalog::AREAS[$round][$town];
            $shop = Shop::create([
                'code' => sprintf('QD-%03d', $i + 1),
                'name' => $name,
                'address' => $street.' '.mt_rand(2, 180),
                'town' => $town,
                'lat' => round($latMin + ($latMax - $latMin) * mt_rand(0, 1000) / 1000, 6),
                'lng' => round($lngMin + ($lngMax - $lngMin) * mt_rand(0, 1000) / 1000, 6),
                'phone' => sprintf('3556%d%07d', [7, 8, 9][mt_rand(0, 2)], mt_rand(1000000, 9999999)),
                'contact_name' => $contact,
                'declared_hours' => match ($declared) {
                    'exact' => TrueHours::declared($pattern),
                    'wrong' => TrueHours::declared(TrueHours::STANDARD),
                    'weekdays' => TrueHours::declared($pattern, [1, 2, 3, 4, 5]),
                    default => DeclaredHours::unknown()->toArray(),
                },
                'access_notes' => ShopCatalog::ACCESS_NOTES[$name] ?? null,
                'order_value_cents' => mt_rand(30, str_starts_with($name, 'Supermarket') ? 220 : 150) * 10000,
                'active' => true,
            ]);
            $truth[$shop->id] = ['pattern' => $pattern, 'absent' => $absent];
            $byRound[$round] = ($byRound[$round] ?? collect())->push($shop);
        }

        return [$byRound, $truth];
    }

    /**
     * One template per round, stops in plain nearest-neighbour order from the depot: the way a
     * new dispatcher would draw it on a map, blind to opening hours.
     *
     * @param  Collection<string, Collection<int, Shop>>  $shops
     * @param  list<User>  $drivers
     * @return array<string, array{template: RouteTemplate, shops: list<Shop>}>
     */
    private function templates(Collection $shops, array $drivers, GeoPoint $depot): array
    {
        $templates = [];
        foreach (self::ROUNDS as $key => $round) {
            $ordered = $this->nearestNeighbour($shops[$key]->all(), $depot);
            $template = RouteTemplate::create([
                'name' => $round['name'],
                'weekdays' => $round['weekdays'],
                'default_driver_id' => $drivers[$round['driver']]->id,
                'start_minute' => $round['start'],
                'active' => true,
            ]);
            foreach ($ordered as $i => $shop) {
                RouteTemplateStop::create(['route_template_id' => $template->id, 'shop_id' => $shop->id, 'position' => $i + 1]);
            }
            $templates[$key] = ['template' => $template, 'shops' => $ordered];
        }

        return $templates;
    }

    /** @param array<string, array{template: RouteTemplate, shops: list<Shop>}> $templates */
    private function history(array $templates, array $drivers, VisitSimulator $simulator, LocalTime $clock): void
    {
        $today = $clock->today();
        $models = null;
        for ($daysAgo = self::HISTORY_DAYS; $daysAgo >= 1; $daysAgo--) {
            $date = $today->subDays($daysAgo);
            $optimised = $daysAgo <= self::OPTIMISED_DAYS;
            if ($optimised && ($models === null || $daysAgo === 7)) {
                $models = $this->learnedModels($templates);
            }
            foreach (self::ROUNDS as $key => $round) {
                if (! in_array($date->dayOfWeekIso, $round['weekdays'], true)) {
                    continue;
                }
                $shops = array_values(array_filter($templates[$key]['shops'], fn () => mt_rand(1, 100) <= 90));
                $driver = mt_rand(1, 100) <= 8 ? $drivers[($round['driver'] + 1) % 3] : $drivers[$round['driver']];
                if ($optimised) {
                    $start = $round['start'];
                    $shops = $this->optimisedOrder($shops, $models, $date->dayOfWeekIso, $start);
                } else {
                    $start = $round['start'] + [-30, 0, 0, 15, 30, 45, 60, 90][mt_rand(0, 7)];
                    $shops = $this->driversOwnOrder($shops);
                }
                $run = $simulator->drive($shops, $date, $start);
                $this->storeTrip($templates[$key]['template'], $driver, $date, $start, $run, $optimised);
            }
        }
    }

    /** Today's trips were optimised and published yesterday; the ones already under way have progressed until now. */
    private function today(array $templates, VisitSimulator $simulator, LocalTime $clock, User $owner): void
    {
        $today = $clock->today();
        $planner = app(TripPlanner::class);
        foreach (app(TripGenerator::class)->generate($today) as $trip) {
            $optimised = $planner->optimise($trip)['after'];
            $planner->reorder($trip, array_column($optimised['stops'], 'id'), true);
            $trip->forceFill(['published_at' => $today->subDay()->setTime(17, 20)->utc()])->save();

            $stops = $trip->stops()->with('shop')->get();
            $run = $simulator->drive($stops->pluck('shop')->all(), $today, $trip->start_minute, $clock->now());
            if (! array_filter($run['stops'], fn (array $s) => $s['visits'])) {
                continue;
            }
            foreach ($run['stops'] as $i => $stop) {
                if ($stop['visits']) {
                    $this->recordVisits($stops[$i], $stop);
                }
            }
            $trip->forceFill([
                'status' => $run['finishedAt'] ? Trip::DONE : Trip::IN_PROGRESS,
                'started_at' => $run['startedAt']->utc(),
                'finished_at' => $run['finishedAt']?->utc(),
            ])->save();
            $this->recount($trip);
        }
    }

    private function storeTrip(RouteTemplate $template, User $driver, CarbonImmutable $date, int $start, array $run, bool $optimised): void
    {
        $trip = Trip::create([
            'route_template_id' => $template->id,
            'name' => $template->name,
            'date' => $date->toDateString(),
            'driver_id' => $driver->id,
            'status' => Trip::DONE,
            'start_minute' => $start,
            'started_at' => $run['startedAt']->utc(),
            'finished_at' => $run['finishedAt']->utc(),
            'published_at' => $date->subDay()->setTime(17, mt_rand(0, 50))->utc(),
            'optimised_at' => $optimised ? $date->subDay()->setTime(16, mt_rand(30, 59))->utc() : null,
        ]);

        $rows = [];
        foreach ($run['stops'] as $i => $stop) {
            $rows[] = [
                'organization_id' => $trip->organization_id,
                'trip_id' => $trip->id,
                'shop_id' => $stop['shop']->id,
                'position' => $i + 1,
                'amount_due_cents' => $stop['due'],
                'created_at' => $trip->created_at,
                'updated_at' => $trip->created_at,
            ];
        }
        DB::table('trip_stops')->insert($rows);
        $stops = TripStop::where('trip_id', $trip->id)->orderBy('position')->get();
        foreach ($run['stops'] as $i => $stop) {
            if ($stop['visits']) {
                $this->recordVisits($stops[$i], $stop);
            } else {
                $stops[$i]->forceFill(['outcome' => TripStop::SKIPPED])->save();
            }
        }
        $this->recount($trip);
    }

    /** Writes the stop's final outcome and one observation per visit. */
    private function recordVisits(TripStop $row, array $stop): void
    {
        $last = $stop['visits'][count($stop['visits']) - 1];
        $row->forceFill([
            'outcome' => $last['outcome'],
            'outcome_at' => $last['at']->utc(),
            'outcome_lat' => $stop['gps']['lat'],
            'outcome_lng' => $stop['gps']['lng'],
            'gps_accuracy_m' => $stop['gps']['accuracy'],
            'distance_m' => (new GeoPoint($stop['gps']['lat'], $stop['gps']['lng']))->metresTo($stop['shop']->point()),
            'note' => $stop['note'],
            'amount_due_cents' => $stop['due'],
            'amount_collected_cents' => $stop['collected'],
            'visits' => count($stop['visits']),
        ])->save();

        $observations = [];
        foreach ($stop['visits'] as $visit) {
            $open = in_array($visit['outcome'], TripStop::SHOP_OPEN, true);
            $local = $visit['at'];
            $observations[] = [
                'organization_id' => $row->organization_id,
                'shop_id' => $row->shop_id,
                'trip_stop_id' => $row->id,
                'observed_at' => $local->utc(),
                'weekday' => $local->dayOfWeekIso,
                'minute_of_day' => $local->hour * 60 + $local->minute,
                'is_open' => $open,
                'outcome' => $visit['outcome'],
                'source' => Observation::FROM_VISIT,
                'created_at' => $local->utc(),
                'updated_at' => $local->utc(),
            ];
            $this->seen[$row->shop_id][] = new Sighting($local->dayOfWeekIso, $local->hour * 60 + $local->minute, $open);
        }
        DB::table('observations')->insert($observations);
    }

    private function recount(Trip $trip): void
    {
        $delivered = TripStop::where('trip_id', $trip->id)->where('outcome', TripStop::DELIVERED);
        $trip->forceFill([
            'cash_expected_cents' => (int) (clone $delivered)->sum('amount_due_cents'),
            'cash_collected_cents' => (int) (clone $delivered)->sum('amount_collected_cents'),
        ])->save();
    }

    /** What the optimiser would know on that day: the visits so far. */
    private function learnedModels(array $templates): array
    {
        $models = [];
        foreach ($templates as $round) {
            foreach ($round['shops'] as $shop) {
                $models[$shop->id] = OpeningHoursModel::learn($shop->declaredHours(), $this->seen[$shop->id] ?? []);
            }
        }

        return $models;
    }

    /** @param list<Shop> $shops @return list<Shop> */
    private function optimisedOrder(array $shops, array $models, int $weekday, int $start): array
    {
        $byId = collect($shops)->keyBy('id');
        $optimizer = new RouteOptimizer(
            $shops[0]->organization->depot(),
            $start,
            array_map(fn (Shop $s) => RouteStop::fromModel($s->id, $s->point(), $models[$s->id], $weekday), $shops),
        );

        return array_map(fn (int $id) => $byId[$id], $optimizer->optimise(array_map(fn (Shop $s) => $s->id, $shops))->order());
    }

    /** Before the dispatcher sequenced the rounds, each driver took them in his own order. @param list<Shop> $shops */
    private function driversOwnOrder(array $shops): array
    {
        $roll = mt_rand(1, 100);
        if ($roll <= 25) {
            $shops = array_reverse($shops);
        } elseif ($roll <= 55) {
            $k = mt_rand(3, max(3, count($shops) - 3));
            $shops = array_merge(array_slice($shops, $k), array_slice($shops, 0, $k));
        }
        for ($swaps = mt_rand(1, 4); $swaps > 0; $swaps--) {
            $i = mt_rand(0, count($shops) - 2);
            [$shops[$i], $shops[$i + 1]] = [$shops[$i + 1], $shops[$i]];
        }

        return $shops;
    }

    /** @param list<Shop> $shops @return list<Shop> */
    private function nearestNeighbour(array $shops, GeoPoint $from): array
    {
        $ordered = [];
        while ($shops) {
            usort($shops, fn (Shop $a, Shop $b) => $from->kmTo($a->point()) <=> $from->kmTo($b->point()));
            $next = array_shift($shops);
            $ordered[] = $next;
            $from = $next->point();
        }

        return $ordered;
    }
}
