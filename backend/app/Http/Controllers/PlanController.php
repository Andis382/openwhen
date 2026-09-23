<?php

namespace App\Http\Controllers;

use App\Hours\Slots;
use App\Models\RouteTemplate;
use App\Models\Trip;
use App\Models\TripStop;
use App\Models\User;
use App\Support\LocalTime;
use App\Support\Tenant;
use App\Trips\TripGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/** One day of trips for the dispatcher: what exists, what still has to be generated. */
class PlanController extends Controller
{
    public function day(Request $request, string $date): JsonResponse
    {
        return response()->json($this->payload($this->parseDate($request, $date)));
    }

    public function generate(Request $request, TripGenerator $generator): JsonResponse
    {
        $data = $request->validate(['date' => ['required', 'date_format:Y-m-d']]);
        $day = $this->parseDate($request, $data['date']);
        $created = $generator->generate($day);

        return response()->json($this->payload($day) + ['created' => $created->count()]);
    }

    private function payload(CarbonImmutable $day): array
    {
        $trips = Trip::with('driver')->whereDate('date', $day->toDateString())->orderBy('start_minute')->orderBy('name')->get();
        $totals = TripStop::whereIn('trip_id', $trips->pluck('id'))
            ->toBase()
            ->selectRaw('trip_id, count(*) as stops, count(outcome) as done, sum(1 - coalesce(planned_p_open, 0.5)) as expected_closed,
                max(planned_eta_minute) as last_eta, coalesce(sum(amount_due_cents), 0) as due')
            ->groupBy('trip_id')
            ->get()
            ->keyBy('trip_id');
        $withTrips = $trips->pluck('route_template_id')->filter()->all();

        return [
            'date' => $day->toDateString(),
            'weekday' => $day->dayOfWeekIso,
            'trips' => $trips->map(fn (Trip $t) => $t->toApi() + [
                'stops' => (int) ($totals[$t->id]->stops ?? 0),
                'done' => (int) ($totals[$t->id]->done ?? 0),
                'expectedClosed' => round((float) ($totals[$t->id]->expected_closed ?? 0), 2),
                'lastEta' => isset($totals[$t->id]->last_eta) ? Slots::format((int) $totals[$t->id]->last_eta % 1440) : null,
                'amountDueCents' => (int) ($totals[$t->id]->due ?? 0),
            ])->values(),
            'templatesDue' => RouteTemplate::where('active', true)->orderBy('name')->get()
                ->filter(fn (RouteTemplate $r) => $r->runsOn($day->dayOfWeekIso) && ! in_array($r->id, $withTrips, true))
                ->map(fn (RouteTemplate $r) => ['id' => $r->id, 'name' => $r->name])
                ->values(),
            'drivers' => User::where('organization_id', Tenant::id())->where('role', User::DRIVER)->orderBy('name')->get(['id', 'name'])
                ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name]),
        ];
    }

    private function parseDate(Request $request, string $date): CarbonImmutable
    {
        Validator::make(['date' => $date], ['date' => ['required', 'date_format:Y-m-d']])->validate();

        return LocalTime::for($request->user()->organization)->date($date);
    }
}
