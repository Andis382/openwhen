@extends('layouts.app')
@section('title', $shop->name)

@section('content')
<div class="page-head">
    <span class="micro">{{ $shop->code ?: __('ui.nav.shops') }}</span>
    <h1>{{ $shop->name }}</h1>
    <p>{{ $shop->location() }}</p>
</div>

<div class="figures">
    <div class="figure {{ $wasted['rate'] >= 15 ? 'is-bad' : ($wasted['rate'] >= 8 ? 'is-warn' : 'is-ok') }}">
        <span class="micro">{{ __('ui.shops.wasted') }}</span>
        <span class="v">{{ $wasted['wasted'] }}</span>
        <span class="foot">{{ __('ui.shops.of_visits', ['rate' => $wasted['rate']]) }}</span>
    </div>
    <div class="figure">
        <span class="micro">{{ __('ui.shops.visits', ['n' => '']) }}</span>
        <span class="v">{{ $wasted['visits'] }}</span>
    </div>
</div>

{{--
    Seven rows, one per weekday, one cell per hour. Every cell is somebody's
    tap. The lunch closure that nobody ever wrote down anywhere shows up as a
    pale stripe through the middle of an otherwise solid row — which is this
    entire product in one picture.
--}}
<h2>{{ __('ui.shops.open_hours') }}</h2>
<div class="panel">
    <div class="heat">
        <table>
            <thead>
                <tr>
                    <th><span class="sr-only">{{ __('ui.route.on_date') }}</span></th>
                    @for ($hour = $first; $hour <= $last; $hour++)
                        <th scope="col">{{ $hour % 3 === 0 ? $hour : '' }}</th>
                    @endfor
                </tr>
            </thead>
            <tbody>
                @foreach ($profiles as $weekday => $profile)
                    <tr>
                        <th scope="row">{{ __('ui.shops.weekday.'.$weekday) }}</th>
                        @for ($hour = $first; $hour <= $last; $hour++)
                            @php($bucket = $profile['hours'][$hour])
                            @php($known = $profile['known'] && $bucket['known'])
                            @php($band = ! $known ? '' : match (true) {
                                $bucket['p'] >= 0.8 => 'p5',
                                $bucket['p'] >= 0.6 => 'p4',
                                $bucket['p'] >= 0.4 => 'p3',
                                $bucket['p'] >= 0.25 => 'p2',
                                default => 'p1',
                            })
                            <td class="{{ $known ? 'known '.$band : '' }}">
                                <span class="sr-only">{{ __('ui.shops.cell', [
                                    'weekday' => __('ui.shops.weekday.'.$weekday),
                                    'hour' => sprintf('%02d:00', $hour),
                                    'verdict' => __('ui.shops.verdict.'.(! $known ? 'unknown' : ($bucket['p'] >= 0.6 ? 'open' : 'shut'))),
                                    'n' => $bucket['n'],
                                ]) }}</span>
                            </td>
                        @endfor
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="heat-key" aria-hidden="true">
        <span><i class="heat-swatch-open"></i>{{ __('ui.shops.key_open') }}</span>
        <span><i class="heat-swatch-shut"></i>{{ __('ui.shops.key_shut') }}</span>
        <span><i class="heat-swatch-unknown"></i>{{ __('ui.shops.key_unknown') }}</span>
    </div>

    <dl class="spec" style="margin-top:var(--s4)">
        @foreach ($profiles as $weekday => $profile)
            <div>
                <dt>{{ __('ui.shops.weekday.'.$weekday) }}</dt>
                <dd>
                    @if (! $profile['known'])
                        <span class="faint">{{ __('ui.shops.not_enough') }}</span>
                    @else
                        @if ($profile['open_from'] !== null)
                            <span class="num">{{ __('ui.shops.window', [
                                'from' => sprintf('%02d:00', $profile['open_from']),
                                'to' => sprintf('%02d:00', $profile['open_to'] + 1),
                            ]) }}</span>
                        @endif
                        @foreach ($profile['rules'] as $rule)
                            <span class="note">{{ __('plan.rule.'.$rule['key'], ['time' => sprintf('%02d:00', $rule['hour'])]) }}</span>
                        @endforeach
                        <span class="note">{{ __('ui.shops.visits', ['n' => $profile['visits']]) }}</span>
                    @endif
                </dd>
            </div>
        @endforeach
    </dl>
</div>

<h2>{{ __('ui.shops.recent') }}</h2>
<div class="panel flush">
    <div class="rows">
        @forelse ($visits as $visit)
            <div class="entry">
                <span class="glyph {{ $visit->tone() }}"><x-icon :name="$visit->icon()" size="19" /></span>
                <span class="entry-body">
                    <span class="entry-title">{{ $visit->outcomeLabel() }}</span>
                    <span class="entry-sub">
                        <span class="num">{{ $visit->observed_at->timezone($company->timezone)->format('D d/m H:i') }}</span>
                        @if ($visit->driver) · {{ $visit->driver->shortName() }} @endif
                        @if ($visit->note)<br>{{ $visit->note }}@endif
                    </span>
                </span>
            </div>
        @empty
            <div class="empty">
                <x-icon name="shops" size="36" />
                <p>{{ __('ui.shops.never') }}</p>
            </div>
        @endforelse
    </div>
</div>

<details class="more">
    <summary><x-icon name="edit" size="18" />{{ __('ui.shops.save') }}</summary>
    <div class="inner">
        <form method="post" action="{{ route('shops.update', $shop) }}">
            @csrf @method('PUT')
            <x-field name="name" :label="__('ui.shops.name')" :value="$shop->name" required maxlength="120" />
            <div class="cols2">
                <x-field name="code" :label="__('ui.shops.code')" :value="$shop->code" optional maxlength="40" />
                <x-field name="phone" type="tel" :label="__('ui.shops.phone')" :value="$shop->phone" optional inputmode="tel" />
            </div>
            <div class="cols2">
                <x-field name="address" :label="__('ui.shops.address')" :value="$shop->address" optional maxlength="160" />
                <x-field name="area" :label="__('ui.shops.area')" :value="$shop->area" optional maxlength="80" />
            </div>
            <div class="cols2">
                <x-field name="lat" type="number" :label="__('ui.shops.lat')" :value="$shop->lat" step="0.0000001" optional inputmode="decimal" />
                <x-field name="lng" type="number" :label="__('ui.shops.lng')" :value="$shop->lng" step="0.0000001" optional inputmode="decimal" />
            </div>
            <x-field name="note" control="textarea" :label="__('ui.shops.note')" :value="$shop->note" maxlength="500" />
            <button class="btn block"><x-icon name="check" size="18" />{{ __('ui.shops.save') }}</button>
        </form>
    </div>
</details>
@endsection
