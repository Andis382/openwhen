<?php

namespace App\Http\Controllers;

use App\Hours\DeclaredHours;
use App\Hours\InvalidHours;
use App\Models\RouteTemplate;
use App\Models\RouteTemplateStop;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Standing rounds: the shops a van covers, in order, and on which weekdays. */
class RouteTemplateController extends Controller
{
    public function index(): JsonResponse
    {
        $templates = RouteTemplate::with(['defaultDriver', 'stops.shop:id,name,town'])->orderBy('name')->get();

        return response()->json($templates->map(fn (RouteTemplate $t) => $t->toApi() + [
            'stopCount' => $t->stops->count(),
            'towns' => $t->stops->pluck('shop.town')->filter()->countBy()->sortDesc()->keys()->take(3)->values(),
            'firstStops' => $t->stops->take(3)->pluck('shop.name')->filter()->values(),
        ]));
    }

    public function show(RouteTemplate $template): JsonResponse
    {
        return response()->json($this->detail($template));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $template = DB::transaction(fn () => $this->save(new RouteTemplate, $data));

        return response()->json($this->detail($template), 201);
    }

    public function update(Request $request, RouteTemplate $template): JsonResponse
    {
        $data = $this->validated($request);
        DB::transaction(fn () => $this->save($template, $data));

        return response()->json($this->detail($template->fresh()));
    }

    public function destroy(RouteTemplate $template): Response
    {
        $template->delete();

        return response()->noContent();
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'weekdays' => ['required', 'array', 'min:1'],
            'weekdays.*' => ['integer', 'between:1,7', 'distinct'],
            'defaultDriverId' => ['nullable', 'integer', Rule::exists('users', 'id')->where('organization_id', Tenant::id())],
            'startTime' => ['required', 'string'],
            'active' => ['sometimes', 'boolean'],
            'shopIds' => ['present', 'array', 'max:200'],
            'shopIds.*' => ['integer', 'distinct', Rule::exists('shops', 'id')->where('organization_id', Tenant::id())],
        ]);
        try {
            $data['startMinute'] = DeclaredHours::parseTime($data['startTime']);
        } catch (InvalidHours) {
            throw ValidationException::withMessages(['startTime' => [__('hours.time')]]);
        }

        return $data;
    }

    private function save(RouteTemplate $template, array $data): RouteTemplate
    {
        $weekdays = array_map('intval', $data['weekdays']);
        sort($weekdays);
        $template->fill([
            'name' => trim($data['name']),
            'weekdays' => $weekdays,
            'default_driver_id' => $data['defaultDriverId'] ?? null,
            'start_minute' => $data['startMinute'],
            'active' => $data['active'] ?? true,
        ])->save();

        RouteTemplateStop::where('route_template_id', $template->id)->delete();
        foreach (array_values($data['shopIds']) as $i => $shopId) {
            RouteTemplateStop::create(['route_template_id' => $template->id, 'shop_id' => $shopId, 'position' => $i + 1]);
        }

        return $template;
    }

    private function detail(RouteTemplate $template): array
    {
        $template->load('defaultDriver', 'stops.shop');

        return $template->toApi() + [
            'stops' => $template->stops->map(fn (RouteTemplateStop $s) => [
                'shopId' => $s->shop_id,
                'position' => $s->position,
                'name' => $s->shop->name,
                'address' => $s->shop->address,
                'town' => $s->shop->town,
                'lat' => $s->shop->lat,
                'lng' => $s->shop->lng,
                'active' => $s->shop->active,
            ])->values(),
        ];
    }
}
