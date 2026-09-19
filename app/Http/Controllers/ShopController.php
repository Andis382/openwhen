<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\InCompany;
use App\Models\Shop;
use App\Services\OpenWindowEstimator;
use Illuminate\Http\Request;

class ShopController extends Controller
{
    use InCompany;

    public function index(Request $request, OpenWindowEstimator $estimator)
    {
        $company = $this->company();
        $q = trim((string) $request->query('q'));

        $shops = $company->shops()
            ->when($q !== '', fn ($query) => $query->where(function ($inner) use ($q) {
                $inner->where('name', 'like', "%{$q}%")
                    ->orWhere('code', 'like', "%{$q}%")
                    ->orWhere('address', 'like', "%{$q}%")
                    ->orWhere('area', 'like', "%{$q}%");
            }))
            ->withCount('visits')
            ->get();

        return view('shops.index', [
            'company' => $company,
            'shops' => $shops,
            'q' => $q,
            'today' => $company->today(),
        ]);
    }

    /**
     * One shop's week, as a grid of weekday against hour.
     *
     * The heat map is the whole point of the product made visible: every cell
     * is somebody's tap, and the lunch closure nobody ever wrote down shows up
     * as a pale stripe through the middle of an otherwise dark row.
     */
    public function show(Request $request, Shop $shop, OpenWindowEstimator $estimator)
    {
        $this->ours($shop);

        $visits = $shop->visits()->with('driver')->orderByDesc('observed_at')->get();
        $byWeekday = $visits->groupBy('weekday');

        $profiles = [];
        foreach (range(1, 7) as $weekday) {
            $profiles[$weekday] = $estimator->profile($shop, $weekday, $byWeekday->get($weekday, collect()));
        }

        return view('shops.show', [
            'company' => $this->company(),
            'shop' => $shop,
            'profiles' => $profiles,
            'visits' => $visits->take(25),
            'wasted' => $estimator->wasted($visits),
            'first' => OpenWindowEstimator::FIRST_HOUR,
            'last' => OpenWindowEstimator::LAST_HOUR,
        ]);
    }

    public function store(Request $request)
    {
        $company = $this->company();
        $data = $this->rules($request);

        $shop = $company->shops()->create($data + ['active' => true]);

        return redirect()->route('shops.show', $shop)->with('status', __('flash.shop_added', ['name' => $shop->name]));
    }

    public function update(Request $request, Shop $shop)
    {
        $this->ours($shop);
        $shop->update($this->rules($request) + ['active' => $request->boolean('active', true)]);

        return back()->with('status', __('flash.saved'));
    }

    private function rules(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:160'],
            'area' => ['nullable', 'string', 'max:80'],
            'phone' => ['nullable', 'string', 'max:30'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);
    }
}
