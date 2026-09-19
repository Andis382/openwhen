<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
@include('partials.head')
<title>@yield('title') · {{ __('ui.app_name') }}</title>
</head>
<body>
<a class="skip" href="#main">{{ __('ui.a11y.skip') }}</a>

<header class="topbar">
    <a class="brand" href="{{ route('today') }}">
        <span class="mark" aria-hidden="true"><x-icon name="van" size="16" /></span>
        Open<span>When</span>
    </a>
    <div class="row">
        {{-- The driver has to be able to see, without asking, that his taps
             are being kept rather than lost. --}}
        <span class="netstate" id="netstate" role="status"
              data-offline="{{ __('ui.net.offline') }}"
              data-pending="{{ __('ui.net.sending') }}"
              data-queued="{{ __('ui.net.queued') }}">
            <x-icon name="offline" size="14" />
            <span id="netstate-text"></span>
        </span>
        <span class="who">
            <strong>{{ auth()->user()->shortName() }}</strong>
            {{ auth()->user()->roleLabel() }}
        </span>
        <a class="iconbtn" href="{{ route('settings') }}" aria-label="{{ __('ui.nav.settings') }}"
           @if (request()->routeIs('settings')) aria-current="page" @endif>
            <x-icon name="dots" size="22" />
        </a>
    </div>
</header>

<main class="page @yield('pageclass')" id="main" tabindex="-1">
    @include('partials.flash')
    @yield('content')
</main>

<nav class="tabbar" aria-label="{{ __('ui.a11y.main_nav') }}">
    <a href="{{ route('today') }}" @if (request()->routeIs('today')) aria-current="page" @endif>
        <x-icon name="navigate" size="22" />
        <span class="label">{{ __('ui.nav.today') }}</span>
    </a>
    <a href="{{ route('routes.index') }}" @if (request()->routeIs('routes.*')) aria-current="page" @endif>
        <x-icon name="route" size="22" />
        <span class="label">{{ __('ui.nav.routes') }}</span>
    </a>
    <a href="{{ route('shops.index') }}" @if (request()->routeIs('shops.*')) aria-current="page" @endif>
        <x-icon name="shops" size="22" />
        <span class="label">{{ __('ui.nav.shops') }}</span>
    </a>
    <a href="{{ route('settings') }}" @if (request()->routeIs('settings')) aria-current="page" @endif>
        <x-icon name="sliders" size="22" />
        <span class="label">{{ __('ui.nav.settings') }}</span>
    </a>
</nav>

@stack('scripts')
<script src="{{ asset('js/outbox.js') }}?v=1" defer></script>
</body>
</html>
