<?php

namespace App\Hours;

use App\Models\Observation;
use App\Models\Shop;

/**
 * Loads the sightings behind shops and learns their hours: one query for any number of shops.
 * Only the last half year counts, so a shop that changed its hours in spring is not held to its
 * winter habits forever.
 */
class ShopHours
{
    public const HISTORY_DAYS = 180;

    /** @var array<int, list<Sighting>> */
    private array $sightings = [];

    /** @var array<int, OpeningHoursModel> */
    private array $models = [];

    /** @var array<int, list<HoursRule>> */
    private array $rules = [];

    /**
     * @param  iterable<Shop>  $shops
     * @return array<int, OpeningHoursModel> keyed by shop id
     */
    public function models(iterable $shops): array
    {
        $shops = collect($shops);
        $this->load($shops->pluck('id')->all());

        return $shops->mapWithKeys(fn (Shop $shop) => [$shop->id => $this->model($shop)])->all();
    }

    public function model(Shop $shop): OpeningHoursModel
    {
        $this->load([$shop->id]);

        return $this->models[$shop->id] ??= OpeningHoursModel::learn($shop->declaredHours(), $this->sightings[$shop->id]);
    }

    /** @return list<Sighting> */
    public function sightings(Shop $shop): array
    {
        $this->load([$shop->id]);

        return $this->sightings[$shop->id];
    }

    /** @return list<HoursRule> */
    public function rules(Shop $shop): array
    {
        return $this->rules[$shop->id] ??= (new RuleExtractor)->extract($this->model($shop), $this->sightings($shop));
    }

    /** Declared against observed, per weekday. */
    public function comparison(Shop $shop): array
    {
        return (new HoursComparison)->compare($this->model($shop), $this->sightings($shop), $this->rules($shop));
    }

    /** @param list<int> $shopIds */
    private function load(array $shopIds): void
    {
        $missing = array_values(array_filter($shopIds, fn (int $id) => ! isset($this->sightings[$id])));
        if (! $missing) {
            return;
        }
        foreach ($missing as $id) {
            $this->sightings[$id] = [];
        }
        Observation::query()
            ->whereIn('shop_id', $missing)
            ->where('observed_at', '>=', now()->subDays(self::HISTORY_DAYS))
            ->orderBy('observed_at')
            ->toBase()
            ->select(['shop_id', 'weekday', 'minute_of_day', 'is_open'])
            ->lazy(2000)
            ->each(function (object $row) {
                $this->sightings[$row->shop_id][] = new Sighting((int) $row->weekday, (int) $row->minute_of_day, (bool) $row->is_open);
            });
    }
}
