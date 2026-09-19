@extends('layouts.app')
@section('title', __('ui.route.title'))

@section('content')
<div class="page-head">
    <span class="micro">{{ $company->name }}</span>
    <h1>{{ __('ui.route.title') }}</h1>
</div>

<div class="panel flush">
    <div class="rows">
        @forelse ($routes as $route)
            <a class="entry" href="{{ route('routes.show', $route) }}">
                <span class="glyph {{ $route->on_date->isSameDay($today) ? '' : 'warn' }}">
                    <x-icon name="route" size="19" />
                </span>
                <span class="entry-body">
                    <span class="entry-title">{{ $route->title() }}</span>
                    <span class="entry-sub">
                        <span class="num">{{ $route->on_date->format('D d/m') }}</span>
                        · {{ __('ui.route.stops', ['n' => $route->stops->count()]) }}
                        @if ($route->driver) · {{ $route->driver->shortName() }} @endif
                    </span>
                </span>
                <span class="entry-side">
                    @if ($route->sequenced_at)
                        <x-tag tone="ok" icon="check">{{ __('ui.route.sequence') }}</x-tag>
                    @else
                        <x-tag icon="clock">{{ __('ui.route.never_sequenced') }}</x-tag>
                    @endif
                </span>
            </a>
        @empty
            <div class="empty">
                <x-icon name="route" size="40" />
                <p>{{ __('ui.route.none') }}</p>
            </div>
        @endforelse
    </div>
</div>

<h2>{{ __('ui.route.new') }}</h2>
<form method="post" action="{{ route('routes.store') }}" class="panel">
    @csrf
    <x-field name="name" :label="__('ui.route.name')" :hint="__('ui.route.name_hint')" optional maxlength="80" />
    <div class="cols2">
        <x-field name="on_date" type="date" :label="__('ui.route.on_date')" :value="$today->copy()->addDay()->toDateString()" required />
        <x-field name="user_id" control="select" :label="__('ui.route.driver')"
                 :options="['' => __('ui.today.unassigned')] + $drivers->mapWithKeys(fn ($d) => [$d->id => $d->name])->all()" />
    </div>
    <button class="btn block"><x-icon name="plus" size="18" />{{ __('ui.route.create') }}</button>
</form>
@endsection
