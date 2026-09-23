<?php

namespace Tests\Feature;

use App\Models\TripStop;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Feature\Concerns\BuildsDistributor;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use BuildsDistributor, RefreshDatabase;

    public function test_dashboard_and_reports_count_visits_per_driver_route_and_week(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-21 12:00', 'Europe/Tirane'));
        $owner = $this->owner();
        $driver = $this->driverOf($owner);
        $shops = [$this->shopOf($owner, ['order_value_cents' => 3000]), $this->shopOf($owner), $this->shopOf($owner)];
        $trip = $this->tripOf($owner, $shops, '2026-09-21', $driver);
        [$a, $b, $c] = $trip->stops()->get()->all();

        $this->actingAs($driver);
        $this->record($a, TripStop::DELIVERED, '08:10', ['amountCollectedCents' => 2500]);
        $this->record($b, TripStop::CLOSED, '08:30');
        $this->record($c, TripStop::OWNER_ABSENT, '08:50');
        $this->record($b, TripStop::DELIVERED, '11:30', ['revisit' => true]);

        $this->asNewVisitor();
        $this->actingAs($owner)->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('trips.0.done', 3)
            ->assertJsonPath('trips.0.closed', 1)
            ->assertJsonPath('trips.0.cashCollectedCents', 2500 + 4500)
            ->assertJsonPath('closedRate.week.visits', 4)
            ->assertJsonPath('closedRate.week.closed', 1)
            ->assertJsonPath('closedRate.week.rate', 0.25)
            ->assertJsonPath('problemShops.0.id', $shops[1]->id)
            ->assertJsonCount(30, 'daily')
            ->assertJsonStructure(['hoursToFix' => ['total', 'shops']]);

        $this->getJson('/api/reports?from=2026-09-01&to=2026-09-21')
            ->assertOk()
            ->assertJsonPath('byDriver.0.name', $driver->name)
            ->assertJsonPath('byDriver.0.visits', 4)
            ->assertJsonPath('byRoute.0.closed', 1)
            ->assertJsonPath('byWeek.3.week', '2026-09-21')
            ->assertJsonPath('byWeek.3.closed', 1)
            ->assertJsonPath('cash.0.expectedCents', 3000 + 4500)
            ->assertJsonPath('cash.0.collectedCents', 2500 + 4500);

        $this->getJson('/api/map?weekday=1&time=08:00')
            ->assertOk()
            ->assertJsonPath('at.isNow', false)
            ->assertJsonCount(3, 'shops');
    }

    private function record(TripStop $stop, string $outcome, string $time, array $extra = []): void
    {
        $this->postJson("/api/driver/stops/{$stop->id}/outcome", $extra + [
            'clientUuid' => (string) Str::uuid(),
            'outcome' => $outcome,
            'at' => "2026-09-21T{$time}:00+02:00",
        ])->assertOk();
    }
}
