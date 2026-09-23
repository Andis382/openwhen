<?php

namespace App\Http\Controllers;

use App\Hours\HoursComparison;
use App\Hours\HoursRule;
use App\Hours\OpenState;
use App\Hours\ShopHours;
use App\Hours\Slots;
use App\Http\Requests\ShopRequest;
use App\Messaging\Messenger;
use App\Models\Observation;
use App\Models\RouteTemplate;
use App\Models\Shop;
use App\Models\TripStop;
use App\Support\LocalTime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ShopController extends Controller
{
    public function __construct(private readonly ShopHours $hours) {}

    /** The customer list with an "open now?" answer for each shop. */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'town' => ['nullable', 'string', 'max:80'],
            'status' => ['nullable', 'in:active,inactive,all'],
        ]);
        $clock = LocalTime::for($request->user()->organization);
        [$weekday, $minute] = $clock->weekdayMinute($clock->now());

        $shops = Shop::query()
            ->when($request->input('status', 'active') !== 'all', fn (Builder $q) => $q->where('active', $request->input('status', 'active') === 'active'))
            ->when($request->filled('town'), fn (Builder $q) => $q->where('town', $request->input('town')))
            ->when($request->filled('q'), function (Builder $q) use ($request) {
                $term = '%'.str_replace(['%', '_'], ['\%', '\_'], trim($request->input('q'))).'%';
                $q->where(fn (Builder $w) => $w->whereLike('name', $term)->orWhereLike('address', $term)
                    ->orWhereLike('contact_name', $term)->orWhereLike('code', $term));
            })
            ->orderBy('name')
            ->limit(1000)
            ->get();

        $models = $this->hours->models($shops);
        $stats = $this->recentVisits();

        return response()->json([
            'at' => ['weekday' => $weekday, 'time' => Slots::format($minute)],
            'towns' => Shop::query()->distinct()->orderBy('town')->pluck('town'),
            'shops' => $shops->map(fn (Shop $shop) => $shop->toApi() + [
                'now' => OpenState::at($models[$shop->id], $weekday, $minute),
                'visits30' => (int) ($stats[$shop->id]->visits ?? 0),
                'closed30' => (int) ($stats[$shop->id]->closed ?? 0),
                'observations' => count($this->hours->sightings($shop)),
                'hoursDisagree' => collect($this->hours->comparison($shop))->contains('status', HoursComparison::MISMATCH),
            ]),
        ]);
    }

    public function store(ShopRequest $request): JsonResponse
    {
        $shop = Shop::create($request->shopAttributes() + ['active' => true]);

        return response()->json($shop->toApi(), 201);
    }

    /** Everything the shop page shows: the learned grid, its rules and the declared/observed check. */
    public function show(Request $request, Shop $shop): JsonResponse
    {
        $clock = LocalTime::for($request->user()->organization);
        [$weekday, $minute] = $clock->weekdayMinute($clock->now());
        $model = $this->hours->model($shop);
        $sightings = $this->hours->sightings($shop);

        return response()->json([
            'shop' => $shop->toApi(),
            'now' => OpenState::at($model, $weekday, $minute),
            'grid' => $model->toArray(),
            'rules' => array_map(fn (HoursRule $r) => $r->toArray(), $this->hours->rules($shop)),
            'comparison' => $this->hours->comparison($shop),
            'observationCount' => count($sightings),
            'historyDays' => ShopHours::HISTORY_DAYS,
            'routes' => RouteTemplate::whereHas('stops', fn (Builder $q) => $q->where('shop_id', $shop->id))
                ->orderBy('name')->get(['id', 'name'])->map(fn (RouteTemplate $r) => ['id' => $r->id, 'name' => $r->name]),
        ]);
    }

    public function update(ShopRequest $request, Shop $shop): JsonResponse
    {
        $shop->update($request->shopAttributes());

        return response()->json($shop->toApi());
    }

    /**
     * Asks the shop on WhatsApp for its real opening hours: the message goes through the outbox,
     * in the distributor's language, and can be sent from the dispatcher's phone when no
     * WhatsApp account is connected.
     */
    public function askHours(Request $request, Shop $shop, Messenger $messenger): JsonResponse
    {
        abort_if($shop->phone === null, 422, __('errors.no_phone'));
        $organization = $request->user()->organization;
        $message = $messenger->send(
            $organization->id,
            $shop->phone,
            $shop->contact_name,
            'hours_check',
            $organization->locale,
            ['name' => $shop->contact_name ?: $shop->name, 'shop' => $shop->name, 'business' => $organization->name],
            relatedType: 'shop',
            relatedId: $shop->id,
        );

        return response()->json($message->toApi(), 201);
    }

    /** The raw sightings behind the heatmap, newest first. */
    public function observations(Request $request, Shop $shop): JsonResponse
    {
        $page = Observation::where('shop_id', $shop->id)->latest('observed_at')->paginate(20);

        return response()->json([
            'data' => collect($page->items())->map(fn (Observation $o) => $o->toApi()),
            'meta' => ['page' => $page->currentPage(), 'pages' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    /** Deliveries and attempts at this shop, newest first. */
    public function visits(Request $request, Shop $shop): JsonResponse
    {
        $page = TripStop::with('trip.driver')
            ->where('shop_id', $shop->id)
            ->whereNotNull('outcome_at')
            ->latest('outcome_at')
            ->paginate(15);

        return response()->json([
            'data' => collect($page->items())->map(fn (TripStop $s) => [
                'id' => $s->id,
                'date' => $s->trip->date->toDateString(),
                'tripId' => $s->trip_id,
                'tripName' => $s->trip->name,
                'driver' => $s->trip->driver?->name,
            ] + $s->outcomeApi()),
            'meta' => ['page' => $page->currentPage(), 'pages' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    /** Visits and closed visits per shop over the last 30 days, keyed by shop id. */
    private function recentVisits(): Collection
    {
        return Observation::query()
            ->where('source', Observation::FROM_VISIT)
            ->where('observed_at', '>=', now()->subDays(30))
            ->toBase()
            ->selectRaw('shop_id, count(*) as visits, sum(case when is_open then 0 else 1 end) as closed')
            ->groupBy('shop_id')
            ->get()
            ->keyBy('shop_id');
    }
}
