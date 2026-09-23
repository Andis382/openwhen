<?php

namespace Tests\Feature;

use App\Models\Observation;
use App\Models\Shop;
use App\Models\Trip;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\BuildsDistributor;
use Tests\TestCase;

class PlanningTest extends TestCase
{
    use BuildsDistributor, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-09-23 16:00', 'Europe/Tirane'));
    }

    public function test_generates_tomorrows_trips_from_the_templates_that_run_that_day(): void
    {
        $owner = $this->owner();
        $driver = $this->driverOf($owner);
        $shops = [$this->shopOf($owner), $this->shopOf($owner), $this->shopOf($owner, ['active' => false])];
        $this->templateOf($owner, $shops, [4], $driver);
        $this->templateOf($owner, [$this->shopOf($owner)], [5]);

        $this->actingAs($owner)->getJson('/api/plan/2026-09-24')
            ->assertOk()
            ->assertJsonPath('weekday', 4)
            ->assertJsonCount(0, 'trips')
            ->assertJsonCount(1, 'templatesDue');

        $this->postJson('/api/plan/generate', ['date' => '2026-09-24'])
            ->assertOk()
            ->assertJsonPath('created', 1)
            ->assertJsonCount(1, 'trips')
            ->assertJsonPath('trips.0.driver.id', $driver->id)
            ->assertJsonPath('trips.0.stops', 2)
            ->assertJsonPath('trips.0.publishedAt', null)
            ->assertJsonCount(0, 'templatesDue');

        $this->postJson('/api/plan/generate', ['date' => '2026-09-24'])->assertOk()->assertJsonPath('created', 0);
        $this->assertNotNull(Trip::sole()->stops()->first()->planned_eta_minute, 'arrival times are planned at once');
    }

    public function test_optimising_moves_the_late_opener_and_applying_saves_the_order(): void
    {
        $owner = $this->owner();
        $driver = $this->driverOf($owner);
        $late = $this->shopOf($owner, ['name' => 'Late opener', 'lat' => 41.3380, 'lng' => 19.7990]);
        $others = [
            $this->shopOf($owner, ['lat' => 41.3300, 'lng' => 19.8200]),
            $this->shopOf($owner, ['lat' => 41.3200, 'lng' => 19.8350]),
            $this->shopOf($owner, ['lat' => 41.3150, 'lng' => 19.8450]),
        ];
        // Thursdays it has been shut at every visit before 10:00, open after.
        foreach (['07:40', '08:10', '08:40', '09:10', '09:35', '09:40', '09:50'] as $time) {
            $this->observe($late, 4, $time, false);
        }
        foreach (['10:05', '10:10', '10:20', '10:25', '10:40', '11:10'] as $time) {
            $this->observe($late, 4, $time, true);
        }
        $trip = $this->tripOf($owner, [$late, ...$others], '2026-09-24', $driver, published: false);
        $trip->update(['start_minute' => 570]);

        $this->actingAs($owner);
        $plan = $this->getJson("/api/trips/{$trip->id}")->assertOk()->json('plan');
        $this->assertSame('opens_later', $plan['stops'][0]['warning']['type']);
        $this->assertSame('10:00', $plan['stops'][0]['warning']['at']);
        $this->assertSame('opens_after', $plan['stops'][0]['rules'][0]['type']);

        $result = $this->postJson("/api/trips/{$trip->id}/optimise")->assertOk()->json();
        $this->assertLessThan($result['before']['summary']['expectedClosed'], $result['after']['summary']['expectedClosed']);
        $this->assertSame('Late opener', end($result['after']['stops'])['shop']['name']);

        $order = array_column($result['after']['stops'], 'id');
        $this->putJson("/api/trips/{$trip->id}/order", ['stopIds' => $order, 'optimised' => true])
            ->assertOk()
            ->assertJsonPath('plan.stops.3.shop.name', 'Late opener');
        $this->assertNotNull($trip->fresh()->optimised_at);
        $this->assertSame(4, $trip->stops()->where('shop_id', $late->id)->value('position'));
    }

    public function test_an_order_must_contain_exactly_the_trips_stops(): void
    {
        $owner = $this->owner();
        $trip = $this->tripOf($owner, [$this->shopOf($owner), $this->shopOf($owner)], '2026-09-24', published: false);
        $ids = $trip->stops()->pluck('id')->all();

        $this->actingAs($owner)->putJson("/api/trips/{$trip->id}/order", ['stopIds' => [$ids[0]]])
            ->assertUnprocessable()->assertJsonValidationErrors(['stopIds']);
        $this->putJson("/api/trips/{$trip->id}/order", ['stopIds' => [$ids[0], $ids[0]]])
            ->assertUnprocessable();
    }

    public function test_publishing_needs_a_driver_and_a_started_trip_is_frozen(): void
    {
        $owner = $this->owner();
        $driver = $this->driverOf($owner);
        $trip = $this->tripOf($owner, [$this->shopOf($owner)], '2026-09-24', published: false);

        $this->actingAs($owner)->postJson("/api/trips/{$trip->id}/publish")
            ->assertUnprocessable()->assertJsonValidationErrors(['driverId']);

        $this->putJson("/api/trips/{$trip->id}", ['driverId' => $driver->id, 'startTime' => '06:30'])
            ->assertOk()->assertJsonPath('trip.startTime', '06:30');
        $this->postJson("/api/trips/{$trip->id}/publish")->assertOk()->assertJsonPath('trip.driver.name', $driver->name);
        $this->assertNotNull($trip->fresh()->published_at);

        $trip->update(['status' => Trip::IN_PROGRESS]);
        $this->postJson("/api/trips/{$trip->id}/optimise")->assertStatus(409);
        $this->deleteJson("/api/trips/{$trip->id}")->assertStatus(409);
    }

    public function test_a_driver_from_another_distributor_cannot_be_assigned(): void
    {
        $owner = $this->owner();
        $stranger = $this->driverOf($this->owner());
        $trip = $this->tripOf($owner, [$this->shopOf($owner)], '2026-09-24', published: false);

        $this->actingAs($owner)->putJson("/api/trips/{$trip->id}", ['driverId' => $stranger->id, 'startTime' => '07:00'])
            ->assertUnprocessable()->assertJsonValidationErrors(['driverId']);
    }

    public function test_route_templates_keep_their_shop_order_and_reject_foreign_shops(): void
    {
        $owner = $this->owner();
        [$a, $b, $c] = [$this->shopOf($owner), $this->shopOf($owner), $this->shopOf($owner)];
        $foreign = $this->shopOf($this->owner());

        $id = $this->actingAs($owner)->postJson('/api/routes', [
            'name' => 'Qendër', 'weekdays' => [6, 1, 3], 'startTime' => '07:30', 'shopIds' => [$c->id, $a->id, $b->id],
        ])->assertCreated()->assertJsonPath('weekdays', [1, 3, 6])->assertJsonPath('stops.0.shopId', $c->id)->json('id');

        $this->putJson("/api/routes/{$id}", ['name' => 'Qendër', 'weekdays' => [1], 'startTime' => '07:30', 'shopIds' => [$a->id, $foreign->id]])
            ->assertUnprocessable()->assertJsonValidationErrors(['shopIds.1']);
        $this->getJson('/api/routes')->assertOk()->assertJsonPath('0.stopCount', 3);
    }

    private function observe(Shop $shop, int $weekday, string $time, bool $open): void
    {
        [$h, $m] = explode(':', $time);
        Observation::create([
            'organization_id' => $shop->organization_id,
            'shop_id' => $shop->id,
            'observed_at' => now()->subDays(7),
            'weekday' => $weekday,
            'minute_of_day' => (int) $h * 60 + (int) $m,
            'is_open' => $open,
            'source' => Observation::FROM_IMPORT,
        ]);
    }
}
