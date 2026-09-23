<?php

namespace Tests\Feature;

use App\Models\Observation;
use App\Models\OutboundMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\BuildsDistributor;
use Tests\TestCase;

class ShopApiTest extends TestCase
{
    use BuildsDistributor, RefreshDatabase;

    private array $shop = [
        'name' => 'Market Ardi',
        'address' => 'Rruga e Kavajës 112',
        'town' => 'Tiranë',
        'lat' => 41.3232,
        'lng' => 19.8012,
        'phone' => '069 123 4567',
        'contactName' => 'Ardian Hoxha',
        'orderValueCents' => 3850,
        'declaredHours' => ['1' => [['10:00', '21:00']], '2' => [['07:00', '13:00'], ['15:00', '21:00']], '7' => []],
    ];

    public function test_creates_a_shop_with_declared_hours(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)->postJson('/api/shops', $this->shop)
            ->assertCreated()
            ->assertJsonPath('name', 'Market Ardi')
            ->assertJsonPath('phone', '355691234567')
            ->assertJsonPath('declaredHours.2', [['07:00', '13:00'], ['15:00', '21:00']])
            ->assertJsonPath('declaredHours.7', [])
            ->assertJsonPath('declaredHours.3', null);
    }

    public function test_unreadable_hours_are_a_field_error(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)->withHeader('Accept-Language', 'sq')
            ->postJson('/api/shops', ['declaredHours' => ['1' => [['13:00', '08:00']]]] + $this->shop)
            ->assertUnprocessable()
            ->assertJsonPath('errors.declaredHours.0', 'Ora e mbylljes duhet të jetë pas orës së hapjes.');
    }

    public function test_the_shop_page_has_the_grid_rules_and_comparison(): void
    {
        $owner = $this->owner();
        $shop = $this->shopOf($owner, ['declared_hours' => ['1' => [['07:00', '21:00']]]]);
        foreach (['07:10', '08:00', '08:40', '09:20', '09:50'] as $time) {
            $this->observe($shop, 1, $time, false);
        }
        foreach (['10:10', '10:40', '11:30', '12:30'] as $time) {
            $this->observe($shop, 1, $time, true);
        }

        $this->actingAs($owner)->getJson("/api/shops/{$shop->id}")
            ->assertOk()
            ->assertJsonCount(7, 'grid')
            ->assertJsonCount(30, 'grid.0.p')
            ->assertJsonPath('rules.0.type', 'opens_after')
            ->assertJsonPath('rules.0.from', '10:00')
            ->assertJsonPath('rules.0.total', 5)
            ->assertJsonPath('comparison.0.status', 'mismatch')
            ->assertJsonPath('observationCount', 9);
    }

    public function test_the_list_searches_and_filters_by_town(): void
    {
        $owner = $this->owner();
        $this->shopOf($owner, ['name' => 'Market Ardi', 'town' => 'Tiranë']);
        $this->shopOf($owner, ['name' => 'Furra Artan', 'town' => 'Kashar']);
        $this->shopOf($owner, ['name' => 'Bulmetore Vlora', 'town' => 'Kashar', 'active' => false]);

        $this->actingAs($owner)->getJson('/api/shops?town=Kashar')
            ->assertOk()
            ->assertJsonCount(1, 'shops')
            ->assertJsonPath('shops.0.name', 'Furra Artan')
            ->assertJsonPath('towns', ['Kashar', 'Tiranë']);

        $this->getJson('/api/shops?q=ardi')->assertJsonCount(1, 'shops')->assertJsonPath('shops.0.name', 'Market Ardi');
        $this->getJson('/api/shops?status=all')->assertJsonCount(3, 'shops');
        $this->getJson('/api/shops')->assertJsonStructure(['shops' => [['now' => ['p', 'state', 'next'], 'visits30', 'hoursDisagree']]]);
    }

    public function test_shops_of_another_distributor_are_invisible(): void
    {
        $mine = $this->owner();
        $theirs = $this->owner();
        $theirShop = $this->shopOf($theirs, ['name' => 'Their shop']);
        $this->shopOf($mine, ['name' => 'My shop']);

        $this->actingAs($mine)->getJson('/api/shops')->assertJsonCount(1, 'shops')->assertJsonPath('shops.0.name', 'My shop');
        $this->getJson("/api/shops/{$theirShop->id}")->assertNotFound();
        $this->putJson("/api/shops/{$theirShop->id}", $this->shop)->assertNotFound();
        $this->getJson("/api/shops/{$theirShop->id}/observations")->assertNotFound();
    }

    public function test_asking_a_shop_for_its_hours_goes_to_the_outbox_in_the_distributors_language(): void
    {
        $owner = $this->owner();
        $owner->organization->update(['locale' => 'sq', 'name' => 'Qumështorja Dajti']);
        $shop = $this->shopOf($owner, ['name' => 'Market Ardi', 'contact_name' => 'Ardian', 'phone' => '355691234567']);
        $silent = $this->shopOf($owner, ['phone' => null]);

        $this->actingAs($owner)->postJson("/api/shops/{$shop->id}/ask-hours")
            ->assertCreated()
            ->assertJsonPath('recipient', '355691234567')
            ->assertJsonPath('templateKey', 'hours_check');
        $this->assertStringContainsString('Përshëndetje Ardian, jemi Qumështorja Dajti', OutboundMessage::sole()->body);

        $this->postJson("/api/shops/{$silent->id}/ask-hours")->assertUnprocessable();
    }

    public function test_drivers_cannot_open_the_shop_list(): void
    {
        $owner = $this->owner();

        $this->actingAs($this->driverOf($owner))->getJson('/api/shops')->assertForbidden();
    }

    private function observe($shop, int $weekday, string $time, bool $open): void
    {
        [$h, $m] = explode(':', $time);
        Observation::create([
            'organization_id' => $shop->organization_id,
            'shop_id' => $shop->id,
            'observed_at' => now()->subDays(3),
            'weekday' => $weekday,
            'minute_of_day' => (int) $h * 60 + (int) $m,
            'is_open' => $open,
            'source' => Observation::FROM_IMPORT,
        ]);
    }
}
