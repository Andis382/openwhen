<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\InCompany;
use App\Models\Route;
use App\Models\Shop;
use App\Models\Stop;
use App\Services\RouteSequencer;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class RouteController extends Controller
{
    use InCompany;

    public function __construct(private readonly RouteSequencer $sequencer)
    {
    }

    public function index(Request $request)
    {
        $company = $this->company();
        $today = $company->today();

        return view('routes.index', [
            'company' => $company,
            'today' => $today,
            'routes' => $company->routes()->with(['driver', 'stops'])
                ->where('on_date', '>=', $today->copy()->subDays(7)->toDateString())
                ->orderByDesc('on_date')->orderBy('id')->get(),
            'drivers' => $company->drivers()->get(),
        ]);
    }

    public function show(Request $request, Route $route)
    {
        $this->ours($route);

        return view('routes.show', [
            'company' => $this->company(),
            'route' => $route->load(['stops.shop', 'driver']),
            'shops' => $this->company()->shops()->where('active', true)->get(),
            'visits' => $route->visits()->get()->keyBy('shop_id'),
        ]);
    }

    public function store(Request $request)
    {
        $company = $this->company();

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:80'],
            'on_date' => ['required', 'date'],
            'user_id' => ['nullable', 'integer'],
        ]);

        $on = Carbon::parse($data['on_date'], $company->timezone)->startOfDay();

        if (isset($data['user_id']) && ! $company->users()->whereKey($data['user_id'])->exists()) {
            throw new NotFoundHttpException();
        }

        $route = $company->routes()->create([
            'name' => $data['name'] ?? null,
            'on_date' => $on->toDateString(),
            'weekday' => (int) $on->isoWeekday(),
            'user_id' => $data['user_id'] ?? null,
        ]);

        return redirect()->route('routes.show', $route)->with('status', __('flash.route_created'));
    }

    /** Reorder for time, using what the drivers have already observed. */
    public function sequence(Request $request, Route $route)
    {
        $this->ours($route);
        $this->sequencer->plan($route);

        return back()->with('status', __('flash.sequenced'));
    }

    /**
     * The same van does the same street every Tuesday. Copy it forward and let
     * the order be the only thing that changes.
     */
    public function repeat(Request $request, Route $route)
    {
        $this->ours($route);

        $data = $request->validate([
            'on_date' => ['required', 'date'],
            'user_id' => ['nullable', 'integer'],
        ]);

        $on = Carbon::parse($data['on_date'], $this->company()->timezone)->startOfDay();
        $copy = $this->sequencer->repeat($route, $on, $data['user_id'] ?? null);

        return redirect()->route('routes.show', $copy)->with('status', __('flash.route_copied'));
    }

    public function addStop(Request $request, Route $route)
    {
        $this->ours($route);

        $data = $request->validate(['shop_id' => ['required', 'integer']]);
        $shop = $this->ours(Shop::find($data['shop_id']));

        $route->stops()->firstOrCreate(
            ['shop_id' => $shop->id],
            ['position' => (int) ($route->stops()->max('position') ?? -1) + 1],
        );

        return back()->with('status', __('flash.stop_added', ['name' => $shop->name]));
    }

    public function removeStop(Request $request, Route $route, Stop $stop)
    {
        $this->ours($route);

        if ((int) $stop->route_id !== (int) $route->id) {
            throw new NotFoundHttpException();
        }

        $stop->delete();

        return back()->with('status', __('flash.stop_removed'));
    }
}
