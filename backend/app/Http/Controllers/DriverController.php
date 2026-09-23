<?php

namespace App\Http\Controllers;

use App\Files\FileStorage;
use App\Hours\HoursRule;
use App\Hours\OpenState;
use App\Hours\ShopHours;
use App\Models\Trip;
use App\Models\TripStop;
use App\Support\LocalTime;
use App\Trips\OutcomeRecorder;
use App\Trips\TripSummary;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The driver's phone. A driver only ever sees trips assigned to them and published by the
 * dispatcher; every write is idempotent so the offline queue can retry freely.
 */
class DriverController extends Controller
{
    public function __construct(
        private readonly ShopHours $hours,
        private readonly OutcomeRecorder $recorder,
    ) {}

    /** Today's trip: the first one not finished yet, else the last one finished. */
    public function today(Request $request): JsonResponse
    {
        $clock = LocalTime::for($request->user()->organization);
        $today = $clock->today()->toDateString();
        $trips = $this->mine($request)->whereDate('date', $today)->orderBy('start_minute')->get();
        $trip = $trips->first(fn (Trip $t) => $t->status !== Trip::DONE) ?? $trips->last();
        $next = $this->mine($request)->whereDate('date', '>', $today)->orderBy('date')->orderBy('start_minute')->first();

        return response()->json([
            'today' => $today,
            'trip' => $trip ? $this->payload($trip) : null,
            'next' => $next ? ['id' => $next->id, 'name' => $next->name, 'date' => $next->date->toDateString(), 'startTime' => $next->toApi()['startTime']] : null,
        ]);
    }

    /** The driver's own recent trips. */
    public function trips(Request $request): JsonResponse
    {
        $today = LocalTime::for($request->user()->organization)->today();
        $trips = $this->mine($request)
            ->whereBetween('date', [$today->subDays(30)->toDateString(), $today->toDateString()])
            ->withCount([
                'stops',
                'stops as delivered_count' => fn ($q) => $q->where('outcome', TripStop::DELIVERED),
                'stops as closed_count' => fn ($q) => $q->where('outcome', TripStop::CLOSED),
            ])
            ->orderByDesc('date')
            ->get();

        return response()->json($trips->map(fn (Trip $t) => $t->toApi() + [
            'stops' => $t->stops_count,
            'delivered' => $t->delivered_count,
            'closed' => $t->closed_count,
        ]));
    }

    public function trip(Request $request, int $id): JsonResponse
    {
        return response()->json($this->payload($this->mine($request)->findOrFail($id)));
    }

    public function start(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['at' => ['nullable', 'date']]);
        $trip = $this->recorder->start($this->mine($request)->findOrFail($id), $data['at'] ?? null);

        return response()->json($this->payload($trip));
    }

    public function finish(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['at' => ['nullable', 'date']]);
        $trip = $this->recorder->finish($this->mine($request)->findOrFail($id), $data['at'] ?? null);

        return response()->json($this->payload($trip->fresh()));
    }

    public function outcome(Request $request, int $id): JsonResponse
    {
        $stop = $this->myStop($request, $id);
        $data = $request->validate([
            'clientUuid' => ['required', 'uuid'],
            'outcome' => ['required', Rule::in(TripStop::OUTCOMES)],
            'at' => ['nullable', 'date'],
            'lat' => ['nullable', 'numeric', 'between:-90,90', 'required_with:lng'],
            'lng' => ['nullable', 'numeric', 'between:-180,180', 'required_with:lat'],
            'accuracy' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'note' => ['nullable', 'string', 'max:500'],
            'amountCollectedCents' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'revisit' => ['sometimes', 'boolean'],
        ]);
        $stop = $this->recorder->record($stop, $data);

        return response()->json($this->stopPayload($stop->fresh(['shop', 'trip'])));
    }

    public function proof(Request $request, int $id): JsonResponse
    {
        $stop = $this->myStop($request, $id);
        $request->validate(['photo' => ['required', 'file', 'max:8192', 'mimetypes:'.implode(',', FileStorage::IMAGES)]]);
        $stop = $this->recorder->attachProof($stop, $request->file('photo'));

        return response()->json($this->stopPayload($stop->fresh(['shop', 'trip'])));
    }

    /** Trips assigned to the signed-in driver that the dispatcher has published. */
    private function mine(Request $request): Builder
    {
        return Trip::where('driver_id', $request->user()->id)->whereNotNull('published_at');
    }

    private function myStop(Request $request, int $id): TripStop
    {
        $stop = TripStop::with('trip')->findOrFail($id);
        abort_unless($stop->trip->driver_id === $request->user()->id && $stop->trip->published_at !== null, 404);

        return $stop;
    }

    private function payload(Trip $trip): array
    {
        $trip->load('stops.shop', 'driver', 'organization');
        $depot = $trip->organization->depot();
        $this->hours->models($trip->stops->pluck('shop'));

        return [
            'trip' => $trip->toApi(),
            'depot' => $depot ? ['name' => $trip->organization->depot_name, 'lat' => $depot->lat, 'lng' => $depot->lng] : null,
            'summary' => TripSummary::of($trip),
            'stops' => $trip->stops->map(fn (TripStop $s) => $this->stopPayload($s, $trip))->values(),
        ];
    }

    /** One stop as the phone shows it, with a hint about the shop's hours at the planned time. */
    private function stopPayload(TripStop $stop, ?Trip $trip = null): array
    {
        $trip ??= $stop->trip;
        $weekday = $trip->weekday();
        $shop = $stop->shop;
        $model = $this->hours->model($shop);

        return [
            'id' => $stop->id,
            'position' => $stop->position,
            'shop' => [
                'id' => $shop->id,
                'name' => $shop->name,
                'address' => $shop->address,
                'town' => $shop->town,
                'lat' => $shop->lat,
                'lng' => $shop->lng,
                'phone' => $shop->phone,
                'contactName' => $shop->contact_name,
                'accessNotes' => $shop->access_notes,
            ],
            'hint' => OpenState::at($model, $weekday, $stop->planned_eta_minute ?? $trip->start_minute),
            'rules' => array_values(array_map(
                fn (HoursRule $r) => $r->toArray(),
                array_filter($this->hours->rules($shop), fn (HoursRule $r) => $r->appliesTo($weekday)),
            )),
        ] + $stop->outcomeApi();
    }
}
