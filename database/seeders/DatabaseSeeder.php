<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Shop;
use App\Models\User;
use App\Models\Visit;
use App\Services\RouteSequencer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * One distributor, three vans, ten weeks of taps.
 *
 * The shops are seeded with real behaviours rather than a flat "open 8 to 8":
 * one never opens before ten on Mondays, one shuts for two hours at lunch, one
 * is closed every Wednesday afternoon for the market, and two are too new to
 * say anything about. Those are the four cases the product has to handle, and
 * a tidy seed would hide every one of them.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        Company::where('name', 'Alba Distribucion')->delete();

        $company = Company::create([
            'name' => 'Alba Distribucion',
            'city' => 'Tiranë',
            'timezone' => 'Europe/Tirane',
        ]);

        $dispatcher = $this->user($company, 'Genti Rama', 'demo@openwhen.test', User::DISPATCHER, 'sq');
        $drivers = [
            $this->user($company, 'Fatmir Dema', 'fatmir@openwhen.test', User::DRIVER, 'sq'),
            $this->user($company, 'Ilir Bata', 'ilir@openwhen.test', User::DRIVER, 'sq'),
        ];

        // name, area, opens, shuts, lunch closure (from, to) or null, shut weekday afternoon or null
        $catalogue = [
            ['Market Vera', 'Kombinat', 7, 20, null, null],
            ['Bar Kafe Elita', 'Kombinat', 6, 22, null, null],
            ['Minimarket Drini', 'Kombinat', 10, 19, null, null],          // never before ten
            ['Furra Bekimi', 'Yzberisht', 6, 14, null, null],
            ['Market Gjergji', 'Yzberisht', 8, 20, [13, 15], null],        // lunch closure
            ['Ushqimore Ana', 'Yzberisht', 8, 19, null, 3],                // shut Wednesday afternoon
            ['Market Besa', 'Astir', 8, 21, null, null],
            ['Bar Rinia', 'Astir', 7, 23, null, null],
            ['Market Liria', 'Astir', 9, 18, [12, 14], null],
            ['Mini Market Ora', 'Astir', 8, 20, null, null],
            ['Market i Ri', 'Kombinat', 8, 20, null, null],                // too new: few visits
            ['Bar Amaro', 'Astir', 9, 23, null, null],                     // too new
        ];

        $shops = [];
        foreach ($catalogue as $i => [$name, $area, $opens, $shuts, $lunch, $shutAfternoon]) {
            $shop = $company->shops()->create([
                'name' => $name,
                'code' => sprintf('A%03d', 100 + $i),
                'area' => $area,
                'address' => 'Rruga '.($i + 3),
                'phone' => '+3556'.mt_rand(10000000, 99999999),
                'lat' => 41.32 + mt_rand(-250, 250) / 10000,
                'lng' => 19.80 + mt_rand(-250, 250) / 10000,
                'active' => true,
            ]);

            $shops[] = [$shop, $opens, $shuts, $lunch, $shutAfternoon, $i >= 10];
        }

        mt_srand(20260920);
        $today = Carbon::now('Europe/Tirane')->startOfDay();

        for ($back = 70; $back >= 1; $back--) {
            $day = $today->copy()->subDays($back);
            $weekday = (int) $day->isoWeekday();

            if ($weekday === 7) {
                continue;   // the vans do not run on Sundays
            }

            foreach ($shops as [$shop, $opens, $shuts, $lunch, $shutAfternoon, $isNew]) {
                // The two newest shops have only been called on for a fortnight.
                if ($isNew && $back > 14) {
                    continue;
                }

                // Each shop is visited two or three times a week, and — this is
                // the part that makes the data usable at all — a van reaches
                // the same shop at roughly the same time each week, because a
                // route has a rhythm. Without that, twenty-five visits scatter
                // across fifteen hours and say nothing about any of them.
                if (mt_rand(0, 100) > 55) {
                    continue;
                }

                $base = 7 + (($shop->id * 3 + $weekday * 2) % 12);
                $hour = min(20, max(6, $base + mt_rand(-1, 1)));
                $open = $hour >= $opens && $hour < $shuts;

                if ($lunch && $hour >= $lunch[0] && $hour < $lunch[1]) {
                    $open = false;
                }

                if ($shutAfternoon !== null && $weekday === $shutAfternoon && $hour >= 13) {
                    $open = false;
                }

                // Shops are not machines: now and then one is shut for no
                // reason anybody records, and now and then one opens early.
                if (mt_rand(0, 100) < 7) {
                    $open = ! $open;
                }

                $outcome = ! $open
                    ? Visit::CLOSED
                    : (mt_rand(0, 100) < 8
                        ? Visit::OWNER_ABSENT
                        : (mt_rand(0, 100) < 4 ? Visit::REFUSED : Visit::DELIVERED));

                $at = $day->copy()->setTime($hour, mt_rand(0, 55));

                $shop->visits()->create([
                    'user_id' => $drivers[array_rand($drivers)]->id,
                    'outcome' => $outcome,
                    'observed_at' => $at,
                    'weekday' => $weekday,
                    'hour' => $hour,
                    'lat' => $shop->lat,
                    'lng' => $shop->lng,
                    'client_uuid' => 'seed-'.$shop->id.'-'.$at->timestamp,
                ]);
            }
        }

        // Today's route, and tomorrow's, so the demo opens on the screen a
        // driver actually uses.
        $sequencer = app(RouteSequencer::class);

        foreach ([['Kombinat', 0], ['Astir', 1]] as $i => [$area, $driverIndex]) {
            $route = $company->routes()->create([
                'user_id' => $drivers[$driverIndex]->id,
                'name' => $area.' · '.$today->locale('sq')->translatedFormat('l'),
                'on_date' => $today->toDateString(),
                'weekday' => (int) $today->isoWeekday(),
            ]);

            foreach ($company->shops()->where('area', $area)->get() as $position => $shop) {
                $route->stops()->create(['shop_id' => $shop->id, 'position' => $position]);
            }

            $sequencer->plan($route);
        }

        $this->command?->info('Seeded Alba Distribucion: demo@openwhen.test / password (Genti, the dispatcher)');
        $this->command?->info('Drivers: fatmir@openwhen.test and ilir@openwhen.test, same password.');
    }

    private function user(Company $company, string $name, string $email, string $role, string $locale): User
    {
        return $company->users()->updateOrCreate(['email' => $email], [
            'name' => $name,
            'password' => 'password',
            'role' => $role,
            'locale' => $locale,
            'timezone' => 'Europe/Tirane',
        ]);
    }
}
