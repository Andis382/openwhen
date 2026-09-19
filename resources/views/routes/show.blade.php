@extends('layouts.app')
@section('title', $route->title())

@section('content')
<div class="page-head">
    <span class="micro">{{ $route->on_date->locale(app()->getLocale())->translatedFormat('l j F') }}</span>
    <h1>{{ $route->title() }}</h1>
    <p>
        {{ $route->driver?->name ?? __('ui.today.unassigned') }}
        · {{ __('ui.route.stops', ['n' => $route->stops->count()]) }}
    </p>
</div>

<div class="actions">
    <form method="post" action="{{ route('routes.sequence', $route) }}">
        @csrf
        <button class="btn"><x-icon name="sort" size="18" />{{ __('ui.route.sequence') }}</button>
    </form>
    @if ($route->sequenced_at)
        <span class="small faint">{{ __('ui.route.sequenced', ['when' => $route->sequenced_at->diffForHumans()]) }}</span>
    @endif
</div>

{{--
    The order, with the reason on every line. A driver who cannot see why his
    list changed drives his old one and taps nothing, and then there is no
    product at all.
--}}
<div class="panel flush">
    <div class="rows">
        @forelse ($route->stops as $stop)
            <div class="entry">
                <span class="glyph">
                    <span class="num" style="font-size:var(--t-xs);font-weight:700">{{ $loop->iteration }}</span>
                </span>
                <span class="entry-body">
                    <span class="entry-title">{{ $stop->shop->name }}</span>
                    <span class="entry-sub">
                        {{ $stop->shop->location() }}
                        <br>{{ $stop->reasonLabel() }}
                    </span>
                </span>
                <span class="entry-side">
                    @if ($stop->suggestedTime())
                        <x-tag tone="brand" icon="clock">
                            <span class="num">{{ $stop->suggestedTime() }}</span>
                        </x-tag>
                    @else
                        <x-tag icon="question">{{ __('ui.shops.key_unknown') }}</x-tag>
                    @endif
                    <form method="post" action="{{ route('routes.stops.destroy', [$route, $stop]) }}">
                        @csrf @method('DELETE')
                        <button class="linkbtn danger">{{ __('ui.route.remove_stop') }}</button>
                    </form>
                </span>
            </div>
        @empty
            <div class="empty">
                <x-icon name="route" size="36" />
                <p>{{ __('ui.route.empty') }}</p>
            </div>
        @endforelse
    </div>
</div>

<form method="post" action="{{ route('routes.stops.store', $route) }}" class="panel">
    @csrf
    <x-field name="shop_id" control="select" :label="__('ui.route.add_stop')"
             :options="$shops->mapWithKeys(fn ($s) => [$s->id => $s->label()])->all()" />
    <button class="btn block"><x-icon name="plus" size="18" />{{ __('ui.route.add_stop') }}</button>
</form>

{{-- The same van does the same street every Tuesday. --}}
<details class="more">
    <summary><x-icon name="retry" size="18" />{{ __('ui.route.repeat') }}</summary>
    <div class="inner">
        <form method="post" action="{{ route('routes.repeat', $route) }}">
            @csrf
            <x-field name="on_date" type="date" :label="__('ui.route.on_date')"
                     :value="$route->on_date->copy()->addWeek()->toDateString()" required />
            <button class="btn block"><x-icon name="route" size="18" />{{ __('ui.route.repeat_do') }}</button>
        </form>
    </div>
</details>
@endsection
