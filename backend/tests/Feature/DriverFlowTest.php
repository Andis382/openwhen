<?php

namespace Tests\Feature;

use App\Models\Observation;
use App\Models\Trip;
use App\Models\TripStop;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Feature\Concerns\BuildsDistributor;
use Tests\TestCase;

class DriverFlowTest extends TestCase
{
    use BuildsDistributor, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Monday 21 September 2026, 09:00 in Tirana
        $this->travelTo(CarbonImmutable::parse('2026-09-21 09:00', 'Europe/Tirane'));
    }

    private function tap(TripStop $stop, string $outcome, array $extra = []): \Illuminate\Testing\TestResponse
    {
        return $this->postJson("/api/driver/stops/{$stop->id}/outcome", $extra + [
            'clientUuid' => (string) Str::uuid(),
            'outcome' => $outcome,
            'at' => '2026-09-21T08:40:00+02:00',
        ]);
    }

    public function test_the_driver_sees_todays_published_trip(): void
    {
        $owner = $this->owner();
        $driver = $this->driverOf($owner);
        $trip = $this->tripOf($owner, [$this->shopOf($owner), $this->shopOf($owner)], '2026-09-21', $driver);

        $this->actingAs($driver)->getJson('/api/driver/today')
            ->assertOk()
            ->assertJsonPath('trip.trip.id', $trip->id)
            ->assertJsonCount(2, 'trip.stops')
            ->assertJsonStructure(['trip' => ['stops' => [['shop' => ['name', 'address', 'phone', 'accessNotes'], 'hint' => ['p', 'state'], 'rules']]]]);
    }

    public function test_a_driver_only_sees_their_own_published_trips(): void
    {
        $owner = $this->owner();
        $me = $this->driverOf($owner, 'Ervin Mema');
        $colleague = $this->driverOf($owner, 'Klodian Duka');
        $theirs = $this->tripOf($owner, [$this->shopOf($owner)], '2026-09-21', $colleague);
        $draft = $this->tripOf($owner, [$this->shopOf($owner)], '2026-09-21', $me, published: false);

        $this->actingAs($me)->getJson('/api/driver/today')->assertOk()->assertJsonPath('trip', null);
        $this->getJson("/api/driver/trips/{$theirs->id}")->assertNotFound();
        $this->getJson("/api/driver/trips/{$draft->id}")->assertNotFound();
        $this->tap($theirs->stops()->first(), TripStop::DELIVERED)->assertNotFound();
        $this->getJson('/api/dashboard')->assertForbidden();
    }

    public function test_a_tap_records_the_outcome_and_an_observation_in_local_time(): void
    {
        $owner = $this->owner();
        $driver = $this->driverOf($owner);
        $shop = $this->shopOf($owner, ['lat' => 41.3300, 'lng' => 19.8200]);
        $trip = $this->tripOf($owner, [$shop], '2026-09-21', $driver);
        $stop = $trip->stops()->first();

        $this->actingAs($driver)->tap($stop, TripStop::CLOSED, ['lat' => 41.3301, 'lng' => 19.8201, 'accuracy' => 12])
            ->assertOk()
            ->assertJsonPath('outcome', 'CLOSED')
            ->assertJsonPath('gps.accuracyM', 12);

        $observation = Observation::sole();
        $this->assertSame(1, $observation->weekday, 'Monday');
        $this->assertSame(8 * 60 + 40, $observation->minute_of_day, '08:40 local, not UTC');
        $this->assertFalse($observation->is_open);
        $this->assertSame('2026-09-21 06:40:00', $observation->observed_at->utc()->format('Y-m-d H:i:s'));
        $this->assertLessThan(30, $stop->fresh()->distance_m);
        $this->assertSame(Trip::IN_PROGRESS, $trip->fresh()->status, 'the first tap starts the trip');
    }

    public function test_the_same_tap_synced_twice_counts_once(): void
    {
        $owner = $this->owner();
        $driver = $this->driverOf($owner);
        $trip = $this->tripOf($owner, [$this->shopOf($owner, ['order_value_cents' => 4200])], '2026-09-21', $driver);
        $stop = $trip->stops()->first();
        $tap = [
            'clientUuid' => '5b0f6c0e-4a8e-4d7e-9a53-2f0c6f1d9a10',
            'outcome' => TripStop::DELIVERED,
            'at' => '2026-09-21T08:40:00+02:00',
            'amountCollectedCents' => 4000,
        ];

        $this->actingAs($driver)->postJson("/api/driver/stops/{$stop->id}/outcome", $tap)->assertOk();
        $this->postJson("/api/driver/stops/{$stop->id}/outcome", $tap)->assertOk()->assertJsonPath('outcome', 'DELIVERED');

        $this->assertSame(1, Observation::count());
        $this->assertSame(1, $stop->fresh()->visits);
        $this->assertSame([4200, 4000], [$trip->fresh()->cash_expected_cents, $trip->fresh()->cash_collected_cents]);
    }

    public function test_a_correction_replaces_the_wrong_tap(): void
    {
        $owner = $this->owner();
        $driver = $this->driverOf($owner);
        $stop = $this->tripOf($owner, [$this->shopOf($owner)], '2026-09-21', $driver)->stops()->first();

        $this->actingAs($driver)->tap($stop, TripStop::CLOSED)->assertOk();
        $this->tap($stop, TripStop::DELIVERED, ['at' => '2026-09-21T08:41:00+02:00'])->assertOk();

        $this->assertSame(1, Observation::count());
        $this->assertTrue(Observation::sole()->is_open);
        $this->assertSame(1, $stop->fresh()->visits);
    }

    public function test_coming_back_later_keeps_both_visits(): void
    {
        $owner = $this->owner();
        $driver = $this->driverOf($owner);
        $stop = $this->tripOf($owner, [$this->shopOf($owner)], '2026-09-21', $driver)->stops()->first();

        $this->actingAs($driver)->tap($stop, TripStop::CLOSED)->assertOk();
        $this->tap($stop, TripStop::DELIVERED, ['at' => '2026-09-21T08:58:00+02:00', 'revisit' => true])
            ->assertOk()
            ->assertJsonPath('visits', 2);

        $this->assertSame([false, true], Observation::orderBy('observed_at')->pluck('is_open')->all());
    }

    public function test_finishing_skips_the_rest_and_reconciles_cash(): void
    {
        $owner = $this->owner();
        $driver = $this->driverOf($owner);
        $a = $this->shopOf($owner, ['order_value_cents' => 5000]);
        $b = $this->shopOf($owner, ['order_value_cents' => 3000]);
        $c = $this->shopOf($owner, ['order_value_cents' => 2000]);
        $trip = $this->tripOf($owner, [$a, $b, $c], '2026-09-21', $driver);
        [$first, $second] = $trip->stops()->get()->all();

        $this->actingAs($driver)->postJson("/api/driver/trips/{$trip->id}/start", ['at' => '2026-09-21T07:05:00+02:00'])
            ->assertOk()->assertJsonPath('trip.status', 'IN_PROGRESS');
        $this->tap($first, TripStop::DELIVERED, ['amountCollectedCents' => 4500])->assertOk();
        $this->tap($second, TripStop::CLOSED)->assertOk();

        $this->postJson("/api/driver/trips/{$trip->id}/finish", ['at' => '2026-09-21T08:55:00+02:00'])
            ->assertOk()
            ->assertJsonPath('trip.status', 'DONE')
            ->assertJsonPath('summary.counts.DELIVERED', 1)
            ->assertJsonPath('summary.counts.CLOSED', 1)
            ->assertJsonPath('summary.counts.SKIPPED', 1)
            ->assertJsonPath('summary.cash.expectedCents', 5000)
            ->assertJsonPath('summary.cash.collectedCents', 4500)
            ->assertJsonPath('summary.cash.differenceCents', -500)
            ->assertJsonPath('summary.shortfalls.0.dueCents', 5000);

        $this->assertSame(2, Observation::count(), 'a skipped stop is not an observation');
        $this->postJson("/api/driver/trips/{$trip->id}/finish")->assertOk()->assertJsonPath('trip.status', 'DONE');
    }

    public function test_a_phone_clock_in_the_future_is_not_trusted(): void
    {
        $owner = $this->owner();
        $driver = $this->driverOf($owner);
        $stop = $this->tripOf($owner, [$this->shopOf($owner)], '2026-09-21', $driver)->stops()->first();

        $this->actingAs($driver)->tap($stop, TripStop::DELIVERED, ['at' => '2026-09-21T15:00:00+02:00'])->assertOk();

        $this->assertSame(9 * 60, Observation::sole()->minute_of_day, 'stamped with the server time, 09:00');
    }

    public function test_proof_photo(): void
    {
        Storage::fake('local');
        $owner = $this->owner();
        $driver = $this->driverOf($owner);
        $stop = $this->tripOf($owner, [$this->shopOf($owner)], '2026-09-21', $driver)->stops()->first();
        $this->actingAs($driver)->tap($stop, TripStop::DELIVERED)->assertOk();

        $url = $this->post("/api/driver/stops/{$stop->id}/proof", ['photo' => UploadedFile::fake()->image('proof.jpg', 800, 600)], ['Accept' => 'application/json'])
            ->assertOk()
            ->json('proofUrl');

        $this->assertStringStartsWith('/api/files/', $url);
        $this->get($url)->assertOk();
    }

    public function test_invalid_taps_are_rejected(): void
    {
        $owner = $this->owner();
        $driver = $this->driverOf($owner);
        $stop = $this->tripOf($owner, [$this->shopOf($owner)], '2026-09-21', $driver)->stops()->first();

        $this->actingAs($driver)->postJson("/api/driver/stops/{$stop->id}/outcome", ['clientUuid' => 'nope', 'outcome' => 'MAYBE'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['clientUuid', 'outcome']);
    }
}
