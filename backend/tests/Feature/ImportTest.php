<?php

namespace Tests\Feature;

use App\Models\Observation;
use App\Models\Shop;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\BuildsDistributor;
use Tests\TestCase;

class ImportTest extends TestCase
{
    use BuildsDistributor, RefreshDatabase;

    private const SHOPS = <<<'CSV'
code,name,address,town,lat,lng,phone,contact,mon,tue,fri,usual order
A-01,Market Ardi,Rruga e Kavajës 112,Tiranë,41.3232,19.8012,069 123 4567,Ardian,10:00-21:00,"07:00-13:00, 15:00-21:00",closed,38.50
A-02,,Rruga e Durrësit 40,Tiranë,41.3301,19.8105,,,,,,
A-03,Furra Artan,Rruga Myslym Shyri 8,Tiranë,91.5,19.81,,,,,,
A-04,Bulmetore Vlora,Rruga e Elbasanit 77,Tiranë,41.3190,19.8320,,,8am-1pm,,,
A-01,Market Ardi 2,Rruga e Kavajës 114,Tiranë,41.3233,19.8013,,,,,,
CSV;

    public function test_the_preview_reports_every_row_and_writes_nothing(): void
    {
        $owner = $this->owner();

        $result = $this->actingAs($owner)->postJson('/api/imports/shops', ['csv' => self::SHOPS])->assertOk()->json();

        $this->assertSame(['rows' => 5, 'valid' => 1, 'invalid' => 4, 'create' => 1, 'update' => 0], $result['counts']);
        $this->assertSame([], $result['rows'][0]['errors']);
        $this->assertSame('name', $result['rows'][1]['errors'][0]['field']);
        $this->assertSame('lat', $result['rows'][2]['errors'][0]['field']);
        $this->assertSame('mon', $result['rows'][3]['errors'][0]['field']);
        $this->assertSame('code', $result['rows'][4]['errors'][0]['field']);
        $this->assertSame(0, Shop::count());
    }

    public function test_confirming_imports_the_valid_rows(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)->postJson('/api/imports/shops', ['csv' => self::SHOPS, 'commit' => true])
            ->assertOk()->assertJsonPath('counts.valid', 1);

        $shop = Shop::sole();
        $this->assertSame('A-01', $shop->code);
        $this->assertSame('355691234567', $shop->phone);
        $this->assertSame(3850, $shop->order_value_cents);
        $this->assertSame([[600, 1260]], $shop->declaredHours()->intervals(1));
        $this->assertSame([], $shop->declaredHours()->intervals(5));
        $this->assertNull($shop->declaredHours()->intervals(3));
    }

    public function test_a_second_import_updates_by_code_and_semicolons_with_albanian_headers_work(): void
    {
        $owner = $this->owner();
        $this->shopOf($owner, ['code' => 'A-01', 'name' => 'Old name', 'phone' => '355691111111']);
        $csv = "Kodi;Emri;Adresa;Qyteti;Lat;Lng\nA-01;Market Ardi;Rruga e Kavajës 112;Tiranë;41,3232;19,8012\n";

        $this->actingAs($owner)->postJson('/api/imports/shops', ['csv' => $csv, 'commit' => true])
            ->assertOk()->assertJsonPath('counts.update', 1);

        $shop = Shop::sole();
        $this->assertSame('Market Ardi', $shop->name);
        $this->assertSame(41.3232, $shop->lat);
        $this->assertSame('355691111111', $shop->phone, 'columns the file does not have are kept');
    }

    public function test_missing_columns_are_named(): void
    {
        $this->actingAs($this->owner())->postJson('/api/imports/shops', ['csv' => "name,town\nMarket Ardi,Tiranë\n"])
            ->assertOk()
            ->assertJsonPath('missingColumns', ['address', 'lat', 'lng']);
    }

    public function test_visit_history_imports_once(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-23 12:00', 'Europe/Tirane'));
        $owner = $this->owner();
        $shop = $this->shopOf($owner, ['code' => 'A-01', 'name' => 'Market Ardi']);
        $csv = "shop,date,time,open\nA-01,2026-09-14,08:40,no\nMarket Ardi,15.09.2026,10:10,po\nUnknown,2026-09-14,08:40,yes\nA-01,2026-09-30,08:40,yes\nA-01,2026-09-14,8am,maybe\n";

        $preview = $this->actingAs($owner)->postJson('/api/imports/observations', ['csv' => $csv])->assertOk()->json();
        $this->assertSame(['rows' => 5, 'valid' => 2, 'invalid' => 3, 'duplicate' => 0], $preview['counts']);
        $this->assertSame('shop', $preview['rows'][2]['errors'][0]['field']);
        $this->assertSame('date', $preview['rows'][3]['errors'][0]['field']);

        $this->postJson('/api/imports/observations', ['csv' => $csv, 'commit' => true])->assertOk();
        $this->postJson('/api/imports/observations', ['csv' => $csv, 'commit' => true])->assertJsonPath('counts.duplicate', 2);

        $this->assertSame(2, Observation::count());
        $first = Observation::orderBy('observed_at')->first();
        $this->assertSame([$shop->id, 1, 520, false, 'IMPORT'], [$first->shop_id, $first->weekday, $first->minute_of_day, $first->is_open, $first->source]);
    }
}
