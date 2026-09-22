<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\User;
use App\Support\Tenant;
use Illuminate\Database\Seeder;

/** A believable sample organisation so the app can be explored at once. */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (User::where('email', config('product.demo_email'))->exists()) {
            return;
        }
        $org = Organization::create(['name' => 'Demo', 'locale' => 'sq', 'timezone' => 'Europe/Tirane']);
        User::create([
            'organization_id' => $org->id,
            'name' => 'Demo Owner',
            'email' => config('product.demo_email'),
            'password' => config('product.demo_password'),
            'role' => User::OWNER,
            'locale' => 'sq',
        ]);

        Tenant::run($org->id, function () {
            // Domain sample data goes here.
        });

        $this->command?->info('Demo data ready. Sign in with '.config('product.demo_email').' / '.config('product.demo_password'));
    }
}
