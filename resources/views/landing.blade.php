@extends('layouts.plain')
@section('title', __('ui.tagline'))
@section('description', 'Every delivery stop becomes an observation of whether the shop was really open. Routes learn each shop true open window, so a replacement driver stops bouncing off shutters on day one.')

@section('topnav')
    <a class="btn ghost small" href="{{ route('login') }}">{{ __('ui.nav.login') }}</a>
    <a class="btn small" href="{{ route('register') }}">{{ __('ui.nav.register') }}</a>
@endsection

@section('content')
<div class="hero">
    <span class="micro">OpenWhen</span>
    <h1>{{ __('ui.tagline') }}</h1>
    <p class="lead">
        Nobody wrote it down. The owner set his hours on Google once in 2019 and then started closing
        for lunch, or for market day, or because he is at the bank. On a dense route, ten to twenty per
        cent of stops find a shutter down — and when a driver quits, the replacement spends weeks
        learning it all over again from scratch.
    </p>
</div>

<div class="flow">
    <div>
        <span class="n">01</span>
        <h3>One tap per stop</h3>
        <p>Delivered, shutter down, owner away, refused. Four buttons the size of half a phone, with the time and the place attached. Works with no signal.</p>
    </div>
    <div>
        <span class="n">02</span>
        <h3>A closed door becomes data</h3>
        <p>Per shop, per weekday, per hour: what fraction of visits found it open. After about six visits a shape appears, and the lunch closure nobody ever recorded shows up as a stripe.</p>
    </div>
    <div>
        <span class="n">03</span>
        <h3>Tomorrow's route is in time order</h3>
        <p>Each stop moved into the hours that shop is usually open — and every stop says why it moved, including "not enough visits yet, left where you had it".</p>
    </div>
</div>

<div class="example">
    <span class="micro">Tuesday, shop 37</span>
    <dl class="spec" style="margin-top:var(--s3)">
        <div><dt>Mon</dt><dd><span class="num">Open 10:00 – 19:00</span><span class="note">rarely open before 10:00 · 9 visits</span></dd></div>
        <div><dt>Tue</dt><dd><span class="num">Open 07:00 – 19:00</span><span class="note">often shut around 13:00 · 11 visits</span></dd></div>
        <div><dt>Wed</dt><dd><span class="faint">Not enough visits yet. About four on a weekday and this fills in.</span></dd></div>
    </dl>
</div>

<h2>What it refuses to do</h2>
<ul class="lead">
    <li><strong>It does not reorder what it has not seen.</strong> A shop with no profile stays exactly where the driver put it. A tool that shuffles somebody's whole list on two weeks of data gets switched off in week three.</li>
    <li><strong>It does not state a rule it has not earned.</strong> "Rarely open before ten" takes three visits in that stretch and near-total agreement. One unlucky Monday at nine proves nothing, and the arithmetic is written so that it cannot pretend otherwise.</li>
    <li><strong>It never joins one distributor's data to another's.</strong> There is no table in the schema that could. A shared open-hours graph is an obvious later product and an obvious later temptation; the moment it exists, what is being shared stops being opening hours and becomes "who calls on which shop, how often".</li>
</ul>

<h2>Why this and not a route optimiser</h2>
<p class="lead">
    OptimoRoute, Routific, Onfleet and the rest take time windows as an input and never learn them —
    and a five-van distributor has nobody who could type those windows in, because nobody knows them.
    FieldAssist and Bizom do learn from visit outcomes, and are enterprise sales-force platforms sold
    to brand-side teams with hundreds of reps and an ERP behind them. Between those two there is a
    distributor with five vans, a customer list in a spreadsheet, and five drivers who already know the
    answer and have never been asked.
</p>

<a class="btn big block" href="{{ route('register') }}">{{ __('ui.nav.register') }}</a>
<p class="center small faint">Open source. Your visits are yours, and there is nowhere in this schema for them to go.</p>
@endsection
