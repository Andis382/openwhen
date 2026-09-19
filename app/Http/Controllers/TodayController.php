<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\InCompany;
use App\Models\Route;
use App\Models\Visit;
use App\Services\OpenWindowEstimator;
use Illuminate\Http\Request;

class TodayController extends Controller
{
    use InCompany;

    public function landing()
    {
        return auth()->check() ? redirect()->route('today') : view('landing');
    }

    public function index(Request $request, OpenWindowEstimator $estimator)
    {
        $company = $this->company();
        $today = $company->today();
        $user = $request->user();

        // A driver sees his own van. A dispatcher sees every van, because that
        // is the difference between the two jobs.
        $routes = $company->routes()
            ->with(['stops.shop', 'driver'])
            ->where('on_date', $today->toDateString())
            ->when(! $user->isDispatcher(), fn ($query) => $query->where('user_id', $user->id))
            ->orderBy('id')
            ->get();

        $done = Visit::query()
            ->whereIn('route_id', $routes->pluck('id'))
            ->get()
            ->groupBy('shop_id');

        $week = Visit::query()
            ->whereIn('shop_id', $company->shops()->pluck('id'))
            ->where('observed_at', '>=', $today->copy()->subDays(6))
            ->get();

        return view('today', [
            'company' => $company,
            'today' => $today,
            'routes' => $routes,
            'done' => $done,
            'week' => $estimator->wasted($week),
            'isDispatcher' => $user->isDispatcher(),
        ]);
    }
}
