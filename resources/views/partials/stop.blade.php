@php($shop = $stop->shop)
@php($visit = $done->get($shop->id)?->first())

{{--
    One stop. Four buttons, two by two, each one the width of half a phone —
    this is tapped once, at a kerb, with the engine running, and a mis-tap does
    not just annoy somebody: it puts a false observation into the shop's
    history and quietly moves its profile.

    Each form carries an empty client_uuid that the outbox fills in before
    anything is sent, so a tap made with no signal can be replayed later
    without ever being counted twice.
--}}
<div class="stop {{ $visit ? 'is-done' : '' }}">
    <div class="stop-head">
        <span class="stop-n">
            <span class="i">{{ $loop->iteration }}</span>
            @if ($stop->suggestedTime())
                <span class="hh">{{ $stop->suggestedTime() }}</span>
            @endif
        </span>

        <div class="stop-body">
            <div class="row between">
                <span class="stop-name grow">{{ $shop->name }}</span>
                @if ($visit)
                    <x-tag :tone="$visit->tone()" :icon="$visit->icon()">{{ $visit->outcomeLabel() }}</x-tag>
                @endif
            </div>

            <span class="stop-where">
                @if ($shop->code)<span class="mono">{{ $shop->code }}</span> · @endif
                {{ $shop->location() }}
            </span>

            @if ($stop->reason)
                <span class="stop-why">{{ $stop->reasonLabel() }}</span>
            @endif

            @if ($visit)
                <div class="stop-done">
                    <x-icon name="check" size="15" />
                    {{ __('ui.stop.recorded', [
                        'what' => $visit->outcomeLabel(),
                        'time' => $visit->observed_at->timezone($company->timezone)->format('H:i'),
                    ]) }}
                </div>
            @else
                <div class="outcomes">
                    @foreach ([
                        ['delivered', 'delivered', 'delivered'],
                        ['closed', 'closed', 'closed'],
                        ['owner_absent', 'absent', 'absent'],
                        ['refused', 'refused', 'refused'],
                    ] as [$outcome, $class, $key])
                        <form method="post" action="{{ route('visits.store') }}" data-outcome-form>
                            @csrf
                            <input type="hidden" name="shop_id" value="{{ $shop->id }}">
                            <input type="hidden" name="route_id" value="{{ $stop->route_id }}">
                            <input type="hidden" name="outcome" value="{{ $outcome }}">
                            <input type="hidden" name="client_uuid" value="">
                            <button class="outcome {{ $class }}">
                                <x-icon :name="$class" size="18" />
                                {{ __('ui.stop.'.$key) }}
                            </button>
                        </form>
                    @endforeach
                </div>
            @endif

            <div class="actions" style="margin-top:var(--s3)">
                <a class="btn ghost small" href="{{ route('shops.show', $shop) }}">
                    <x-icon name="chart" size="15" />{{ __('ui.shops.open_hours') }}
                </a>
                @if ($shop->phone)
                    <a class="btn ghost small" href="tel:{{ $shop->phone }}">
                        <x-icon name="phone" size="15" />{{ __('ui.stop.call') }}
                    </a>
                @endif
                @if ($shop->mapLink())
                    <a class="btn ghost small" href="{{ $shop->mapLink() }}" target="_blank" rel="noopener">
                        <x-icon name="pin" size="15" />{{ __('ui.stop.map') }}
                    </a>
                @endif
            </div>
        </div>
    </div>
</div>
