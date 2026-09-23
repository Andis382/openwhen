<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shop>
 */
class ShopFactory extends Factory
{
    protected $model = Shop::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => 'Market '.fake()->unique()->firstName(),
            'address' => 'Rruga '.fake()->lastName().' '.fake()->numberBetween(1, 80),
            'town' => 'Tiranë',
            'lat' => fake()->randomFloat(6, 41.31, 41.35),
            'lng' => fake()->randomFloat(6, 19.78, 19.85),
            'phone' => '35569'.fake()->numerify('#######'),
            'contact_name' => fake()->firstName(),
            'declared_hours' => array_fill_keys(array_map('strval', range(1, 7)), [['07:00', '21:00']]),
            'order_value_cents' => 4500,
            'active' => true,
        ];
    }
}
