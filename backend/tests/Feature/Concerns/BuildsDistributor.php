<?php

namespace Tests\Feature\Concerns;

use App\Models\Organization;
use App\Models\RouteTemplate;
use App\Models\RouteTemplateStop;
use App\Models\Shop;
use App\Models\Trip;
use App\Models\TripStop;
use App\Models\User;

/** A small distributor to test against: an owner, drivers, shops, routes and trips. */
trait BuildsDistributor
{
    protected function owner(): User
    {
        $organization = Organization::factory()->create([
            'depot_name' => 'Depo, Rruga e Durrësit',
            'depot_lat' => 41.3372,
            'depot_lng' => 19.7968,
        ]);

        return User::factory()->create(['organization_id' => $organization->id]);
    }

    protected function driverOf(User $owner, string $name = 'Ervin Mema'): User
    {
        return User::factory()->driver()->create(['organization_id' => $owner->organization_id, 'name' => $name]);
    }

    protected function shopOf(User $owner, array $attributes = []): Shop
    {
        return Shop::factory()->create(['organization_id' => $owner->organization_id] + $attributes);
    }

    /** @param list<Shop> $shops */
    protected function templateOf(User $owner, array $shops, array $weekdays = [1, 2, 3, 4, 5, 6], ?User $driver = null): RouteTemplate
    {
        $template = RouteTemplate::create([
            'organization_id' => $owner->organization_id,
            'name' => 'Veri',
            'weekdays' => $weekdays,
            'default_driver_id' => $driver?->id,
            'start_minute' => 420,
        ]);
        foreach ($shops as $i => $shop) {
            RouteTemplateStop::create(['route_template_id' => $template->id, 'shop_id' => $shop->id, 'position' => $i + 1]);
        }

        return $template;
    }

    /** @param list<Shop> $shops */
    protected function tripOf(User $owner, array $shops, string $date, ?User $driver = null, bool $published = true): Trip
    {
        $trip = Trip::create([
            'organization_id' => $owner->organization_id,
            'name' => 'Veri',
            'date' => $date,
            'driver_id' => $driver?->id,
            'status' => Trip::PLANNED,
            'start_minute' => 420,
            'published_at' => $published ? now() : null,
        ]);
        foreach ($shops as $i => $shop) {
            TripStop::create([
                'organization_id' => $owner->organization_id,
                'trip_id' => $trip->id,
                'shop_id' => $shop->id,
                'position' => $i + 1,
                'amount_due_cents' => $shop->order_value_cents,
            ]);
        }

        return $trip;
    }

    /** A different browser: no session, no signed-in user. */
    protected function asNewVisitor(): void
    {
        $this->app['auth']->forgetGuards();
        $this->flushSession();
    }
}
