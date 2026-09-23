<?php

namespace App\Trips;

use App\Models\RouteTemplate;
use App\Models\RouteTemplateStop;
use App\Models\Trip;
use App\Models\TripStop;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Turns the route templates that run on a weekday into that date's trips. */
class TripGenerator
{
    public function __construct(private readonly TripPlanner $planner) {}

    /** @return Collection<int, Trip> only the trips created now; existing ones are left alone */
    public function generate(CarbonInterface $date): Collection
    {
        $weekday = $date->dayOfWeekIso;
        $existing = Trip::whereDate('date', $date->toDateString())->whereNotNull('route_template_id')->pluck('route_template_id')->all();

        return RouteTemplate::with('stops.shop')
            ->where('active', true)
            ->orderBy('name')
            ->get()
            ->filter(fn (RouteTemplate $t) => $t->runsOn($weekday) && ! in_array($t->id, $existing, true))
            ->map(fn (RouteTemplate $t) => $this->fromTemplate($t, $date))
            ->filter()
            ->values();
    }

    private function fromTemplate(RouteTemplate $template, CarbonInterface $date): ?Trip
    {
        $shops = $template->stops->map(fn (RouteTemplateStop $s) => $s->shop)->filter(fn ($shop) => $shop !== null && $shop->active)->values();
        if ($shops->isEmpty()) {
            return null;
        }

        return DB::transaction(function () use ($template, $date, $shops) {
            $trip = Trip::create([
                'route_template_id' => $template->id,
                'name' => $template->name,
                'date' => $date->toDateString(),
                'driver_id' => $template->default_driver_id,
                'status' => Trip::PLANNED,
                'start_minute' => $template->start_minute,
            ]);
            foreach ($shops as $i => $shop) {
                TripStop::create([
                    'trip_id' => $trip->id,
                    'shop_id' => $shop->id,
                    'position' => $i + 1,
                    'amount_due_cents' => $shop->order_value_cents,
                ]);
            }
            $this->planner->refreshEtas($trip);

            return $trip;
        });
    }
}
