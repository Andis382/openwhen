<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\InCompany;
use App\Models\Route;
use App\Models\Shop;
use App\Models\Visit;
use App\Services\VisitRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VisitController extends Controller
{
    use InCompany;

    public function __construct(private readonly VisitRecorder $recorder)
    {
    }

    public function store(Request $request)
    {
        $data = $this->rules($request);
        $shop = $this->ours(Shop::find($data['shop_id']));
        $route = isset($data['route_id']) ? Route::find($data['route_id']) : null;

        $this->recorder->record(
            shop: $shop,
            by: $request->user(),
            outcome: $data['outcome'],
            route: $route && (int) $route->company_id === (int) $this->company()->id ? $route : null,
            clientUuid: $data['client_uuid'] ?? null,
            lat: isset($data['lat']) ? (float) $data['lat'] : null,
            lng: isset($data['lng']) ? (float) $data['lng'] : null,
            note: $data['note'] ?? null,
        );

        return back()->with('status', __('flash.visit_recorded'));
    }

    /**
     * What the phone's outbox posts to when it gets signal back.
     *
     * Answers 200 for a visit it has already seen, so a phone that never got
     * the first reply can retry for as long as it likes without turning one
     * closed shutter into three.
     */
    public function sync(Request $request): JsonResponse
    {
        $data = $this->rules($request);
        $shop = $this->ours(Shop::find($data['shop_id']));
        $route = isset($data['route_id']) ? Route::find($data['route_id']) : null;

        $result = $this->recorder->record(
            shop: $shop,
            by: $request->user(),
            outcome: $data['outcome'],
            at: isset($data['observed_at']) ? \Illuminate\Support\Carbon::parse($data['observed_at']) : null,
            route: $route && (int) $route->company_id === (int) $this->company()->id ? $route : null,
            clientUuid: $data['client_uuid'] ?? null,
            lat: isset($data['lat']) ? (float) $data['lat'] : null,
            lng: isset($data['lng']) ? (float) $data['lng'] : null,
            note: $data['note'] ?? null,
        );

        return response()->json([
            'ok' => true,
            'created' => $result['created'],
            'id' => $result['visit']->id,
            'client_uuid' => $result['visit']->client_uuid,
        ]);
    }

    private function rules(Request $request): array
    {
        return $request->validate([
            'shop_id' => ['required', 'integer'],
            'route_id' => ['nullable', 'integer'],
            'outcome' => ['required', Rule::in(Visit::OUTCOMES)],
            'client_uuid' => ['nullable', 'string', 'max:64'],
            'observed_at' => ['nullable', 'date'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'note' => ['nullable', 'string', 'max:300'],
        ]);
    }
}
