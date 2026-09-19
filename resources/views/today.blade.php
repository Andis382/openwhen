@extends('layouts.app')
@section('title', __('ui.today.title'))

@section('content')
<div class="page-head">
    <span class="micro">{{ $today->locale(app()->getLocale())->translatedFormat('l j F') }}</span>
    <h1>{{ $company->name }}</h1>
</div>

<div class="figures">
    <div class="figure {{ $week['rate'] >= 15 ? 'is-bad' : ($week['rate'] >= 8 ? 'is-warn' : 'is-ok') }}">
        <span class="micro">{{ __('ui.today.wasted') }}</span>
        <span class="v">{{ $week['wasted'] }}</span>
        <span class="foot">{{ __('ui.shops.of_visits', ['rate' => $week['rate']]) }} · {{ __('ui.today.wasted_foot') }}</span>
    </div>
    <div class="figure">
        <span class="micro">{{ __('ui.today.stops') }}</span>
        <span class="v">{{ $routes->sum(fn ($route) => $route->stops->count()) }}</span>
        <span class="foot">{{ __('ui.common.today') }}</span>
    </div>
</div>

@forelse ($routes as $route)
    @php($doneCount = $route->stops->filter(fn ($stop) => $done->has($stop->shop_id))->count())
    <h2>{{ $route->title() }}</h2>
    <div class="panel flush lead-primary">
        <div class="panel-head">
            <span class="micro">
                {{ $route->driver?->shortName() ?? __('ui.today.unassigned') }}
                · {{ __('ui.route.stops', ['n' => $route->stops->count()]) }}
            </span>
            <x-tag :tone="$doneCount === $route->stops->count() ? 'ok' : 'brand'" icon="check">
                {{ $doneCount }} / {{ $route->stops->count() }}
            </x-tag>
        </div>

        @forelse ($route->stops as $stop)
            @include('partials.stop', ['stop' => $stop, 'done' => $done, 'company' => $company])
        @empty
            <div class="empty">
                <x-icon name="route" size="36" />
                <p>{{ __('ui.route.empty') }}</p>
            </div>
        @endforelse

        <div class="panel-foot">
            <a class="btn ghost small" href="{{ route('routes.show', $route) }}">
                <x-icon name="sliders" size="15" />{{ __('ui.nav.routes') }}
            </a>
            @if ($route->sequenced_at)
                <span class="small faint">{{ __('ui.route.sequenced', ['when' => $route->sequenced_at->diffForHumans()]) }}</span>
            @else
                <span class="small faint">{{ __('ui.route.never_sequenced') }}</span>
            @endif
        </div>
    </div>
@empty
    <div class="panel">
        <div class="empty">
            <x-icon name="route" size="40" />
            <p>{{ __('ui.today.no_route_body') }}</p>
        </div>
        @if ($isDispatcher)
            <a class="btn block big" href="{{ route('routes.index') }}">
                <x-icon name="plus" size="20" />{{ __('ui.today.build') }}
            </a>
        @endif
    </div>
@endforelse
@endsection
