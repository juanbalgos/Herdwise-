@extends('layouts.app')

@section('title', 'Farm reports · HerdWise')
@section('heading', 'Farm reports')

@section('content')
@php
    $exportQuery = ['start_date' => $startDate->toDateString(), 'end_date' => $endDate->toDateString(), 'section' => $section];
    $show = fn (string $name): bool => in_array($section, ['all', $name], true);
    $largestSpeciesTotal = max(1, (int) $inventory['species']->max(fn (array $item): int => $item['individuals'] + $item['batch_population']));
@endphp

<div class="pagebar report-pagebar">
    <div>
        <span class="eyebrow">FARM INSIGHTS</span>
        <h2>A clearer view of {{ $farm->name }}.</h2>
        <p>Review livestock, care, breeding, growth, and scheduled work in one protected report.</p>
    </div>
    <div class="button-row">
        <a class="outline compact" href="{{ route('reports.csv', $exportQuery) }}">↓ Export CSV</a>
        <a class="btn" href="{{ route('reports.pdf', $exportQuery) }}">↓ Download PDF</a>
    </div>
</div>

@if($errors->any())
    <div class="errors"><b>Please update the report filters.</b><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif

<form class="panel report-filter" method="get" action="{{ route('reports.index') }}">
    <label>From
        <input type="date" name="start_date" value="{{ $startDate->toDateString() }}">
    </label>
    <label>To
        <input type="date" name="end_date" value="{{ $endDate->toDateString() }}">
    </label>
    <label>Report section
        <select name="section">
            <option value="all" @selected($section === 'all')>Complete farm report</option>
            <option value="inventory" @selected($section === 'inventory')>Livestock inventory</option>
            <option value="care" @selected($section === 'care')>Care and health</option>
            <option value="reproduction" @selected($section === 'reproduction')>Breeding and births</option>
            <option value="growth" @selected($section === 'growth')>Growth and weight</option>
            <option value="reminders" @selected($section === 'reminders')>Reminders</option>
        </select>
    </label>
    <button class="btn" type="submit">Apply filters</button>
</form>

<div class="report-period">
    <span>{{ $startDate->format('M j, Y') }} — {{ $endDate->format('M j, Y') }}</span>
    <small>Generated {{ $generatedAt->timezone($farm->timezone)->format('M j, Y · g:i A') }}</small>
</div>

<div class="stats report-stats">
    <article><span class="stat-icon green">◉</span><div><small>MANAGED LIVESTOCK</small><strong>{{ number_format($inventory['individuals'] + $inventory['batch_population']) }}</strong><p>{{ $inventory['individuals'] }} individual · {{ $inventory['batch_population'] }} batched</p></div></article>
    <article><span class="stat-icon blue">♡</span><div><small>CARE ACTIVITIES</small><strong>{{ number_format($care['total']) }}</strong><p>{{ $care['high_priority'] }} high-priority observation{{ $care['high_priority'] === 1 ? '' : 's' }}</p></div></article>
    <article><span class="stat-icon lime">♧</span><div><small>LIVE BIRTHS</small><strong>{{ number_format($reproduction['live_births']) }}</strong><p>{{ $reproduction['active_pregnancies'] }} active pregnanc{{ $reproduction['active_pregnancies'] === 1 ? 'y' : 'ies' }}</p></div></article>
    <article><span class="stat-icon amber">◇</span><div><small>OVERDUE TASKS</small><strong>{{ number_format($reminders['overdue']) }}</strong><p>{{ $reminders['pending'] }} pending reminders total</p></div></article>
</div>

@if($show('inventory'))
<section class="panel report-section">
    <div class="panel-head"><div><span class="eyebrow">INVENTORY SNAPSHOT</span><h3>Livestock by species</h3></div><span class="report-badge">Current totals</span></div>
    <div class="inventory-report-grid">
        <div class="species-bars">
            @forelse($inventory['species'] as $species)
                @php($speciesTotal = $species['individuals'] + $species['batch_population'])
                <div class="species-bar-row">
                    <div><b>{{ $species['name'] }}</b><small>{{ $species['individuals'] }} individual · {{ $species['batches'] }} active batch{{ $species['batches'] === 1 ? '' : 'es' }}</small></div>
                    <span><i style="width: {{ max(3, ($speciesTotal / $largestSpeciesTotal) * 100) }}%"></i></span>
                    <strong>{{ number_format($speciesTotal) }}</strong>
                </div>
            @empty
                <div class="empty">No livestock has been registered for this farm.</div>
            @endforelse
        </div>
        <div class="inventory-side">
            <div><small>INDIVIDUAL RECORDS</small><strong>{{ number_format($inventory['individuals']) }}</strong></div>
            <div><small>ACTIVE BATCHES</small><strong>{{ number_format($inventory['active_batches']) }}</strong></div>
            <div><small>BATCH POPULATION</small><strong>{{ number_format($inventory['batch_population']) }}</strong></div>
            <div class="status-list"><small>INDIVIDUAL STATUS</small>@forelse($inventory['statuses'] as $status => $total)<p><span>{{ str_replace('_', ' ', ucfirst($status)) }}</span><b>{{ $total }}</b></p>@empty<p><span>No records</span><b>0</b></p>@endforelse</div>
        </div>
    </div>
</section>
@endif

@if($show('care'))
<section class="panel report-section">
    <div class="panel-head"><div><span class="eyebrow">CARE & HEALTH</span><h3>Activity completed in this period</h3></div><a href="{{ route('activities.index') }}">Open care records →</a></div>
    <div class="report-metric-row">
        <div><small>OBSERVATIONS</small><b>{{ $care['health'] }}</b></div>
        <div><small>TREATMENTS</small><b>{{ $care['treatments'] }}</b></div>
        <div><small>VACCINATIONS</small><b>{{ $care['vaccinations'] }}</b></div>
        <div><small>DEWORMING</small><b>{{ $care['deworming'] }}</b></div>
    </div>
    <div class="table report-table"><div class="tr th"><span>Livestock</span><span>Date</span><span>Observation</span><span>Severity</span></div>
        @forelse($care['recent'] as $record)
            <div class="tr"><span><b>{{ $record->animal?->animal_code ?? $record->batch?->batch_code ?? 'Farm-wide' }}</b><small>{{ $record->animal?->species?->name ?? $record->batch?->species?->name }}</small></span><span>{{ $record->observed_on->format('M j, Y') }}</span><span>{{ $record->observation }}</span><span><em class="pill {{ $record->severity }}">{{ $record->severity }}</em></span></div>
        @empty
            <div class="empty">No health observations match this period.</div>
        @endforelse
    </div>
</section>
@endif

@if($show('reproduction'))
<section class="panel report-section">
    <div class="panel-head"><div><span class="eyebrow">REPRODUCTION</span><h3>Breeding and birth outcomes</h3></div><a href="{{ route('reproduction.index') }}">Open breeding records →</a></div>
    <div class="report-metric-row five">
        <div><small>BREEDING EVENTS</small><b>{{ $reproduction['breeding'] }}</b></div>
        <div><small>PREGNANCY CHECKS</small><b>{{ $reproduction['pregnancy_checks'] }}</b></div>
        <div><small>BIRTH EVENTS</small><b>{{ $reproduction['birth_events'] }}</b></div>
        <div><small>LIVE OFFSPRING</small><b>{{ $reproduction['live_births'] }}</b></div>
        <div><small>STILLBORN</small><b>{{ $reproduction['stillborn'] }}</b></div>
    </div>
    <div class="table report-table"><div class="tr th"><span>Mother</span><span>Date</span><span>Outcome</span><span>Offspring</span></div>
        @forelse($reproduction['recent_births'] as $birth)
            <div class="tr"><span><b>{{ $birth->animal->animal_code }}</b><small>{{ $birth->animal->species->name }}</small></span><span>{{ $birth->delivered_on->format('M j, Y') }}</span><span>{{ str_replace('_', ' ', ucfirst($birth->outcome)) }}</span><span><b>{{ $birth->live_count }} live</b><small>{{ $birth->stillborn_count }} stillborn</small></span></div>
        @empty
            <div class="empty">No birth outcomes match this period.</div>
        @endforelse
    </div>
</section>
@endif

@if($show('growth'))
<section class="panel report-section">
    <div class="panel-head"><div><span class="eyebrow">GROWTH</span><h3>Weight monitoring</h3></div><a href="{{ route('weights.index') }}">Open weight records →</a></div>
    <div class="report-metric-row three">
        <div><small>MEASUREMENTS</small><b>{{ $growth['measurements'] }}</b></div>
        <div><small>INDIVIDUAL / BATCH</small><b>{{ $growth['individual_measurements'] }} / {{ $growth['batch_measurements'] }}</b></div>
        <div><small>AVERAGE RECORDED WEIGHT</small><b>{{ number_format($growth['average_weight'], 2) }} kg</b></div>
    </div>
    <div class="table report-table"><div class="tr th"><span>Livestock</span><span>Date</span><span>Weight</span><span>Recorded by</span></div>
        @forelse($growth['recent'] as $weight)
            <div class="tr"><span><b>{{ $weight->animal?->animal_code ?? $weight->batch?->batch_code }}</b><small>{{ $weight->animal?->species?->name ?? $weight->batch?->species?->name }}</small></span><span>{{ $weight->measured_on->format('M j, Y') }}</span><span><b>{{ number_format((float) $weight->weight_kg, 2) }} kg</b></span><span>{{ $weight->recorder->name }}</span></div>
        @empty
            <div class="empty">No weight measurements match this period.</div>
        @endforelse
    </div>
</section>
@endif

@if($show('reminders'))
<section class="panel report-section">
    <div class="panel-head"><div><span class="eyebrow">FOLLOW-UPS</span><h3>Pending farm reminders</h3></div><a href="{{ route('activities.index') }}">Manage reminders →</a></div>
    <div class="report-reminder-summary"><b>{{ $reminders['pending'] }}</b><span>pending total</span><b class="{{ $reminders['overdue'] ? 'danger-text' : '' }}">{{ $reminders['overdue'] }}</b><span>overdue</span><b>{{ $reminders['due_in_period'] }}</b><span>due in report period</span></div>
    <div class="table report-table"><div class="tr th"><span>Task</span><span>Due</span><span>Livestock</span><span>Priority</span></div>
        @forelse($reminders['upcoming'] as $reminder)
            <div class="tr"><span><b>{{ $reminder->title }}</b><small>{{ str_replace('_', ' ', ucfirst($reminder->type)) }}</small></span><span class="{{ $reminder->due_on->isPast() ? 'danger-text' : '' }}">{{ $reminder->due_on->format('M j, Y') }}</span><span>{{ $reminder->animal?->animal_code ?? $reminder->batch?->batch_code ?? 'Farm-wide' }}</span><span><em class="pill">{{ $reminder->priority }}</em></span></div>
        @empty
            <div class="empty">There are no pending reminders.</div>
        @endforelse
    </div>
</section>
@endif
@endsection
