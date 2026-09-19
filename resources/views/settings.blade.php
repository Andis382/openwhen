@extends('layouts.app')
@section('title', __('ui.settings.title'))
@section('pageclass', 'narrow')

@section('content')
<div class="page-head">
    <span class="micro">{{ $company?->name }}</span>
    <h1>{{ __('ui.settings.title') }}</h1>
</div>

<form method="post" action="{{ route('settings.update') }}" class="panel">
    @csrf @method('PUT')
    <x-field name="name" :label="__('ui.settings.name')" :value="auth()->user()->name" required maxlength="80" />
    <div class="cols2">
        <x-field name="phone" type="tel" :label="__('ui.settings.phone')" :value="auth()->user()->phone" optional inputmode="tel" />
        <x-field name="locale" control="select" :label="__('ui.settings.language')"
                 :options="config('openwhen.locales')" :selected="auth()->user()->locale" />
    </div>

    @if (auth()->user()->isDispatcher() && $company)
        <fieldset>
            <legend>{{ __('ui.settings.company') }}</legend>
            <x-field name="company_name" :label="__('ui.settings.company_name')" :value="$company->name" maxlength="120" />
            <div class="cols2">
                <x-field name="company_city" :label="__('ui.settings.company_city')" :value="$company->city" optional maxlength="80" />
                <x-field name="timezone" :label="__('ui.settings.timezone')" :value="$company->timezone" class="mono" />
            </div>
        </fieldset>
    @endif

    <button class="btn block"><x-icon name="check" size="18" />{{ __('ui.settings.save') }}</button>
</form>

<h2>{{ __('ui.settings.people') }}</h2>
<div class="panel flush">
    <div class="rows">
        @foreach ($people as $person)
            <div class="entry">
                <span class="glyph"><x-icon name="user" size="19" /></span>
                <span class="entry-body">
                    <span class="entry-title">{{ $person->name }}</span>
                    <span class="entry-sub">{{ $person->roleLabel() }}@if ($person->phone) · <span class="mono">{{ $person->phone }}</span>@endif</span>
                </span>
            </div>
        @endforeach
    </div>
</div>

<form method="post" action="{{ route('logout') }}" style="margin-top:var(--s6)">
    @csrf
    <button class="btn ghost block"><x-icon name="logout" size="18" />{{ __('ui.nav.logout') }}</button>
</form>
@endsection
