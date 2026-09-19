<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Route;
use App\Models\Shop;
use App\Models\User;
use App\Models\Visit;
use App\Services\RouteSequencer;
use App\Services\VisitRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RouteAndVisitTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private User $dispatcher;
    private User $driver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create(['name' => 'Alba', 'timezone' => 'Europe/Tirane']);
        $this->dispatcher = $this->person('Genti', 'genti@example.test', User::DISPATCHER);
        $this->driver = $this->person('Fatmir', 'fatmir@example.test', User::DRIVER);
    }

    private function person(string $name, string $email, string $role, ?Company $company = null): User
    {
        return ($company ?? $this->company)->users()->create([
            'name' => $name, 'email' => $email, 'password' => 'secret-secret',
            'role' => $role, 'locale' => 'en', 'timezone' => 'Europe/Tirane',
        ]);
    }

    private function shop(string $name, ?Company $company = null): Shop
    {
        return ($company ?? $this->company)->shops()->create(['name' => $name, 'active' => true]);
    }

    private function seenAt(Shop $shop, int $weekday, int $hour, string $outcome, int $times = 1): void
    {
        foreach (range(1, $times) as $n) {
            $shop->visits()->create([
                'user_id' => $this->driver->id,
                'outcome' => $outcome,
                'observed_at' => now()->subWeeks($n),
                'weekday' => $weekday,
                'hour' => $hour,
            ]);
        }
    }

    private function route(Carbon $on): Route
    {
        return $this->company->routes()->create([
            'user_id' => $this->driver->id,
            'on_date' => $on->toDateString(),
            'weekday' => (int) $on->isoWeekday(),
        ]);
    }

    /**
     * A tap replayed from the phone's outbox must never count twice. A doubled
     * "closed" quietly moves a shop's profile and nobody would ever notice.
     */
    public function test_the_same_tap_sent_twice_is_written_once(): void
    {
        $shop = $this->shop('Market Vera');
        $recorder = app(VisitRecorder::class);

        $first = $recorder->record($shop, $this->driver, Visit::CLOSED, clientUuid: 'abc-123');
        $second = $recorder->record($shop, $this->driver, Visit::CLOSED, clientUuid: 'abc-123');

        $this->assertTrue($first['created']);
        $this->assertFalse($second['created']);
        $this->assertSame((int) $first['visit']->id, (int) $second['visit']->id);
        $this->assertSame(1, Visit::count());
    }

    public function test_two_genuinely_different_taps_are_both_kept(): void
    {
        $shop = $this->shop('Market Vera');
        $recorder = app(VisitRecorder::class);

        $recorder->record($shop, $this->driver, Visit::CLOSED, clientUuid: 'one');
        $recorder->record($shop, $this->driver, Visit::DELIVERED, clientUuid: 'two');

        $this->assertSame(2, Visit::count());
    }

    public function test_the_weekday_and_hour_are_taken_in_the_companys_own_time(): void
    {
        $shop = $this->shop('Market Vera');

        // Midnight UTC is two in the morning in Tirana, and the arithmetic is
        // all local — getting this wrong shifts every observation by an hour.
        $at = Carbon::parse('2026-09-21 00:30', 'UTC');
        $result = app(VisitRecorder::class)->record($shop, $this->driver, Visit::DELIVERED, at: $at);

        $this->assertSame(2, (int) $result['visit']->hour);
        $this->assertSame(1, (int) $result['visit']->weekday);
    }

    public function test_the_sync_endpoint_answers_ok_for_a_tap_it_already_has(): void
    {
        $shop = $this->shop('Market Vera');

        $payload = ['shop_id' => $shop->id, 'outcome' => 'closed', 'client_uuid' => 'replay-1'];

        $this->actingAs($this->driver)->postJson(route('visits.sync'), $payload)
            ->assertOk()->assertJson(['created' => true]);

        $this->actingAs($this->driver)->postJson(route('visits.sync'), $payload)
            ->assertOk()->assertJson(['created' => false]);

        $this->assertSame(1, Visit::count());
    }

    public function test_another_companys_shop_does_not_exist(): void
    {
        $other = Company::create(['name' => 'Someone else', 'timezone' => 'Europe/Tirane']);
        $theirShop = $this->shop('Their market', $other);

        $this->actingAs($this->driver)->post(route('visits.store'), [
            'shop_id' => $theirShop->id,
            'outcome' => 'delivered',
        ])->assertNotFound();

        $this->actingAs($this->driver)->get(route('shops.show', $theirShop))->assertNotFound();
        $this->assertSame(0, Visit::count());
    }

    /** Reorder for time, using what the drivers have already seen. */
    public function test_a_route_is_reordered_into_each_shops_open_window(): void
    {
        $monday = Carbon::parse('2026-09-21', 'Europe/Tirane');

        $late = $this->shop('Never before eleven');
        $early = $this->shop('Open at seven');

        $this->seenAt($late, 1, 12, Visit::DELIVERED, 4);
        $this->seenAt($early, 1, 7, Visit::DELIVERED, 4);

        $route = $this->route($monday);
        $route->stops()->create(['shop_id' => $late->id, 'position' => 0]);
        $route->stops()->create(['shop_id' => $early->id, 'position' => 1]);

        $plan = app(RouteSequencer::class)->plan($route);

        $this->assertSame($early->id, (int) $plan[0]['stop']->shop_id);
        $this->assertSame($late->id, (int) $plan[1]['stop']->shop_id);
        $this->assertSame('window', $plan[0]['reason']);
    }

    /**
     * The rule that keeps this switched on. A tool that shuffles a driver's
     * whole list on its first day, on two weeks of data, is gone by its
     * second.
     */
    public function test_a_shop_with_no_history_is_left_exactly_where_the_driver_put_it(): void
    {
        $monday = Carbon::parse('2026-09-21', 'Europe/Tirane');

        $unknownA = $this->shop('Never visited A');
        $unknownB = $this->shop('Never visited B');

        $route = $this->route($monday);
        $route->stops()->create(['shop_id' => $unknownA->id, 'position' => 0]);
        $route->stops()->create(['shop_id' => $unknownB->id, 'position' => 1]);

        $plan = app(RouteSequencer::class)->plan($route);

        $this->assertSame($unknownA->id, (int) $plan[0]['stop']->shop_id);
        $this->assertSame($unknownB->id, (int) $plan[1]['stop']->shop_id);
        $this->assertSame('unknown', $plan[0]['reason']);
        $this->assertFalse($plan[0]['moved']);
    }

    public function test_unknown_shops_keep_their_order_among_known_ones(): void
    {
        $monday = Carbon::parse('2026-09-21', 'Europe/Tirane');

        $early = $this->shop('Seven');
        $unknown = $this->shop('No idea');
        $late = $this->shop('Six in the evening');

        $this->seenAt($early, 1, 7, Visit::DELIVERED, 4);
        $this->seenAt($late, 1, 18, Visit::DELIVERED, 4);

        $route = $this->route($monday);
        $route->stops()->create(['shop_id' => $late->id, 'position' => 0]);
        $route->stops()->create(['shop_id' => $unknown->id, 'position' => 1]);
        $route->stops()->create(['shop_id' => $early->id, 'position' => 2]);

        $plan = app(RouteSequencer::class)->plan($route);
        $order = array_map(fn (array $row) => (int) $row['stop']->shop_id, $plan);

        // The unknown shop lands between the two it has no argument with,
        // rather than being shoved to the end of the day.
        $this->assertSame([$early->id, $unknown->id, $late->id], $order);
    }

    public function test_only_the_same_weekday_is_read(): void
    {
        $monday = Carbon::parse('2026-09-21', 'Europe/Tirane');
        $shop = $this->shop('Monday shop');

        // Wide open on Saturdays, only ever seen at noon on Mondays.
        $this->seenAt($shop, 6, 7, Visit::DELIVERED, 5);
        $this->seenAt($shop, 1, 12, Visit::DELIVERED, 4);

        $route = $this->route($monday);
        $route->stops()->create(['shop_id' => $shop->id, 'position' => 0]);

        $plan = app(RouteSequencer::class)->plan($route);

        $this->assertSame(12, $plan[0]['hour']);
    }

    public function test_repeating_a_route_copies_the_shops_and_reorders_them(): void
    {
        $monday = Carbon::parse('2026-09-21', 'Europe/Tirane');
        $nextMonday = $monday->copy()->addWeek();

        $shop = $this->shop('Market Vera');
        $this->seenAt($shop, 1, 9, Visit::DELIVERED, 4);

        $route = $this->route($monday);
        $route->stops()->create(['shop_id' => $shop->id, 'position' => 0]);

        $copy = app(RouteSequencer::class)->repeat($route, $nextMonday);

        $this->assertSame($nextMonday->toDateString(), $copy->on_date->toDateString());
        $this->assertSame(1, $copy->stops()->count());
        $this->assertNotNull($copy->sequenced_at);
        $this->assertSame(2, Route::count());
    }

    public function test_a_guest_is_sent_to_the_login_page(): void
    {
        $this->get(route('today'))->assertRedirect(route('login'));
        $this->get(route('shops.index'))->assertRedirect(route('login'));
    }

    public function test_a_driver_sees_only_his_own_van(): void
    {
        $today = $this->company->today();
        $other = $this->person('Ilir', 'ilir@example.test', User::DRIVER);

        $mine = $this->route($today);
        $mine->update(['name' => 'My street']);

        $theirs = $this->company->routes()->create([
            'user_id' => $other->id,
            'name' => 'Their street',
            'on_date' => $today->toDateString(),
            'weekday' => (int) $today->isoWeekday(),
        ]);

        $this->actingAs($this->driver)->get(route('today'))
            ->assertOk()->assertSee('My street')->assertDontSee('Their street');

        // A dispatcher sees every van, because that is the difference between
        // the two jobs.
        $this->actingAs($this->dispatcher)->get(route('today'))
            ->assertOk()->assertSee('My street')->assertSee('Their street');
    }
}
