@extends('layouts.app')
@section('title', __('ui.shops.title'))

@section('content')
<div class="page-head">
    <span class="micro">{{ $company->name }}</span>
    <h1>{{ __('ui.shops.title') }}</h1>
</div>

<form method="get" class="searchbar" role="search">
    <label class="sr-only" for="f-q">{{ __('ui.shops.search') }}</label>
    <input class="grow" type="search" id="f-q" name="q" value="{{ $q }}" placeholder="{{ __('ui.shops.search') }}">
    <button class="btn">
        <x-icon name="search" size="18" />
        <span class="sr-only">{{ __('ui.common.search') }}</span>
    </button>
</form>

<div class="panel flush">
    <div class="rows">
        @forelse ($shops as $shop)
            <a class="entry" href="{{ route('shops.show', $shop) }}">
                <span class="glyph"><x-icon name="shops" size="19" /></span>
                <span class="entry-body">
                    <span class="entry-title">{{ $shop->name }}</span>
                    <span class="entry-sub">
                        @if ($shop->code)<span class="mono">{{ $shop->code }}</span> · @endif
                        {{ $shop->location() }}
                    </span>
                </span>
                <span class="entry-side">
                    @if ($shop->visits_count > 0)
                        <span class="small faint num">{{ __('ui.shops.visits', ['n' => $shop->visits_count]) }}</span>
                    @else
                        <x-tag icon="question">{{ __('ui.shops.never') }}</x-tag>
                    @endif
                </span>
            </a>
        @empty
            <div class="empty">
                <x-icon name="shops" size="40" />
                <p>{{ __('ui.shops.none') }}</p>
            </div>
        @endforelse
    </div>
</div>

<h2>{{ __('ui.shops.add') }}</h2>
<form method="post" action="{{ route('shops.store') }}" class="panel">
    @csrf
    <x-field name="name" :label="__('ui.shops.name')" required maxlength="120" />
    <div class="cols2">
        <x-field name="code" :label="__('ui.shops.code')" optional maxlength="40" />
        <x-field name="phone" type="tel" :label="__('ui.shops.phone')" optional inputmode="tel" />
    </div>
    <div class="cols2">
        <x-field name="address" :label="__('ui.shops.address')" optional maxlength="160" />
        <x-field name="area" :label="__('ui.shops.area')" optional maxlength="80" />
    </div>
    <button class="btn block"><x-icon name="plus" size="18" />{{ __('ui.shops.add') }}</button>
</form>
@endsection
