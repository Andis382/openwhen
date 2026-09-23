<?php

namespace App\Http\Controllers;

use App\Hours\DeclaredHours;
use App\Hours\InvalidHours;
use App\Models\Trip;
use App\Models\User;
use App\Support\Tenant;
use App\Trips\TripPlanner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** A single trip on the planning board: its order, the optimiser, and publishing it to the driver. */
class TripController extends Controller
{
    public function __construct(private readonly TripPlanner $planner) {}

    public function show(Trip $trip): JsonResponse
    {
        return response()->json($this->detail($trip));
    }

    public function update(Request $request, Trip $trip): JsonResponse
    {
        $this->plannedOnly($trip);
        $data = $request->validate([
            'driverId' => ['nullable', 'integer', Rule::exists('users', 'id')->where('organization_id', Tenant::id())->where('role', User::DRIVER)],
            'startTime' => ['required', 'string'],
        ]);
        try {
            $start = DeclaredHours::parseTime($data['startTime']);
        } catch (InvalidHours) {
            throw ValidationException::withMessages(['startTime' => [__('hours.time')]]);
        }
        $trip->update(['driver_id' => $data['driverId'] ?? null, 'start_minute' => $start]);
        $this->planner->refreshEtas($trip);

        return response()->json($this->detail($trip->fresh()));
    }

    /** Before and after; nothing is saved until the dispatcher applies the new order. */
    public function optimise(Trip $trip): JsonResponse
    {
        $this->plannedOnly($trip);

        return response()->json($this->planner->optimise($trip));
    }

    public function order(Request $request, Trip $trip): JsonResponse
    {
        $this->plannedOnly($trip);
        $data = $request->validate([
            'stopIds' => ['required', 'array'],
            'stopIds.*' => ['integer'],
            'optimised' => ['sometimes', 'boolean'],
        ]);
        $ids = array_map('intval', $data['stopIds']);
        $current = $trip->stops()->pluck('id')->all();
        if (count($ids) !== count($current) || array_diff($current, $ids) || count(array_unique($ids)) !== count($ids)) {
            throw ValidationException::withMessages(['stopIds' => [__('errors.order_mismatch')]]);
        }
        $this->planner->reorder($trip, $ids, (bool) ($data['optimised'] ?? false));

        return response()->json($this->detail($trip->fresh()));
    }

    public function publish(Trip $trip): JsonResponse
    {
        $this->plannedOnly($trip);
        if ($trip->driver_id === null) {
            throw ValidationException::withMessages(['driverId' => [__('errors.driver_needed')]]);
        }
        $trip->forceFill(['published_at' => now()])->save();

        return response()->json($this->detail($trip->fresh()));
    }

    public function unpublish(Trip $trip): JsonResponse
    {
        $this->plannedOnly($trip);
        $trip->forceFill(['published_at' => null])->save();

        return response()->json($this->detail($trip->fresh()));
    }

    public function destroy(Trip $trip): Response
    {
        $this->plannedOnly($trip);
        $trip->delete();

        return response()->noContent();
    }

    private function plannedOnly(Trip $trip): void
    {
        abort_unless($trip->isPlanned(), 409, __('errors.trip_started'));
    }

    private function detail(Trip $trip): array
    {
        $trip->load('driver', 'organization');
        $depot = $trip->organization->depot();

        return [
            'trip' => $trip->toApi(),
            'depot' => $depot ? ['name' => $trip->organization->depot_name, 'lat' => $depot->lat, 'lng' => $depot->lng] : null,
            'editable' => $trip->isPlanned(),
            'plan' => $this->planner->current($trip),
        ];
    }
}
