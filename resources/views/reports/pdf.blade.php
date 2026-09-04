<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $farm->name }} farm report</title>
    <style>
        @page { margin: 34px 38px 46px; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #17251f; font-family: "DejaVu Sans", sans-serif; font-size: 9px; line-height: 1.45; }
        .header { background: #123c2d; color: white; padding: 22px 24px; margin: -34px -38px 22px; }
        .brand { font-size: 18px; font-weight: bold; }
        .brand span { color: #65c58d; }
        .header h1 { margin: 18px 0 3px; font-size: 22px; }
        .header p { margin: 0; color: #bdd4c8; }
        .meta { width: 100%; margin-bottom: 18px; border-collapse: collapse; }
        .meta td { width: 33%; padding: 7px 9px; border: 1px solid #dfe8e3; }
        .label, .eyebrow { color: #176b49; font-size: 7px; font-weight: bold; letter-spacing: 1px; text-transform: uppercase; }
        .meta strong { display: block; margin-top: 2px; font-size: 9px; }
        .cards { width: 100%; border-collapse: separate; border-spacing: 7px; margin: 0 -7px 20px; }
        .cards td { width: 25%; vertical-align: top; background: #eef7f2; border: 1px solid #d8e9df; padding: 12px; }
        .cards strong { display: block; color: #123c2d; font-size: 19px; margin: 3px 0; }
        .cards small { color: #6c7b74; font-size: 7px; }
        .section { margin: 0 0 20px; page-break-inside: avoid; }
        h2 { margin: 0 0 8px; font-size: 14px; color: #123c2d; }
        table.data { width: 100%; border-collapse: collapse; }
        .data th { background: #edf5f0; color: #456157; padding: 7px; text-align: left; font-size: 7px; text-transform: uppercase; letter-spacing: .5px; }
        .data td { border-bottom: 1px solid #e3ebe6; padding: 7px; vertical-align: top; }
        .data strong { font-size: 9px; }
        .muted { color: #6c7b74; }
        .metrics { width: 100%; border-collapse: collapse; margin-bottom: 9px; }
        .metrics td { border: 1px solid #dfe8e3; padding: 9px; }
        .metrics b { display: block; color: #176b49; font-size: 14px; }
        .pill { display: inline-block; border-radius: 8px; background: #e8f4ed; color: #176b49; padding: 2px 6px; text-transform: capitalize; }
        .danger { color: #a94739; }
        .empty { color: #6c7b74; border: 1px dashed #cfdcd5; padding: 10px; }
    </style>
</head>
<body>
    @php($show = fn (string $name): bool => in_array($section, ['all', $name], true))
    <header class="header">
        <div class="brand">Herd<span>Wise</span></div>
        <h1>{{ $farm->name }}</h1>
        <p>Operational livestock report</p>
    </header>
    <table class="meta"><tr><td><span class="label">Report period</span><strong>{{ $startDate->format('M j, Y') }} - {{ $endDate->format('M j, Y') }}</strong></td><td><span class="label">Farm location</span><strong>{{ $farm->location ?: 'Not specified' }}</strong></td><td><span class="label">Report section</span><strong>{{ ucfirst($section === 'all' ? 'complete report' : $section) }}</strong></td></tr></table>
    <table class="cards"><tr>
        <td><span class="label">Managed livestock</span><strong>{{ number_format($inventory['individuals'] + $inventory['batch_population']) }}</strong><small>{{ $inventory['individuals'] }} individual; {{ $inventory['batch_population'] }} batched</small></td>
        <td><span class="label">Care activities</span><strong>{{ number_format($care['total']) }}</strong><small>{{ $care['high_priority'] }} high-priority observations</small></td>
        <td><span class="label">Live births</span><strong>{{ number_format($reproduction['live_births']) }}</strong><small>{{ $reproduction['active_pregnancies'] }} active pregnancies</small></td>
        <td><span class="label">Overdue tasks</span><strong class="{{ $reminders['overdue'] ? 'danger' : '' }}">{{ number_format($reminders['overdue']) }}</strong><small>{{ $reminders['pending'] }} pending reminders</small></td>
    </tr></table>

    @if($show('inventory'))
    <section class="section"><span class="eyebrow">Inventory snapshot</span><h2>Livestock by species</h2>
        <table class="data"><thead><tr><th>Species</th><th>Individual records</th><th>Active batches</th><th>Batch population</th><th>Total managed</th></tr></thead><tbody>
        @forelse($inventory['species'] as $species)<tr><td><strong>{{ $species['name'] }}</strong></td><td>{{ $species['individuals'] }}</td><td>{{ $species['batches'] }}</td><td>{{ $species['batch_population'] }}</td><td><strong>{{ number_format($species['individuals'] + $species['batch_population']) }}</strong></td></tr>@empty<tr><td colspan="5" class="muted">No livestock registered.</td></tr>@endforelse
        </tbody></table>
    </section>
    @endif

    @if($show('care'))
    <section class="section"><span class="eyebrow">Care and health</span><h2>Activity completed in this period</h2>
        <table class="metrics"><tr><td><span class="label">Observations</span><b>{{ $care['health'] }}</b></td><td><span class="label">Treatments</span><b>{{ $care['treatments'] }}</b></td><td><span class="label">Vaccinations</span><b>{{ $care['vaccinations'] }}</b></td><td><span class="label">Deworming</span><b>{{ $care['deworming'] }}</b></td></tr></table>
        @if($care['recent']->isNotEmpty())<table class="data"><thead><tr><th>Livestock</th><th>Date</th><th>Observation</th><th>Severity</th></tr></thead><tbody>@foreach($care['recent'] as $record)<tr><td><strong>{{ $record->animal?->animal_code ?? $record->batch?->batch_code ?? 'Farm-wide' }}</strong><br><span class="muted">{{ $record->animal?->species?->name ?? $record->batch?->species?->name }}</span></td><td>{{ $record->observed_on->format('M j, Y') }}</td><td>{{ $record->observation }}</td><td><span class="pill">{{ $record->severity }}</span></td></tr>@endforeach</tbody></table>@else<div class="empty">No health observations match this period.</div>@endif
    </section>
    @endif

    @if($show('reproduction'))
    <section class="section"><span class="eyebrow">Reproduction</span><h2>Breeding and birth outcomes</h2>
        <table class="metrics"><tr><td><span class="label">Breeding</span><b>{{ $reproduction['breeding'] }}</b></td><td><span class="label">Pregnancy checks</span><b>{{ $reproduction['pregnancy_checks'] }}</b></td><td><span class="label">Birth events</span><b>{{ $reproduction['birth_events'] }}</b></td><td><span class="label">Live / stillborn</span><b>{{ $reproduction['live_births'] }} / {{ $reproduction['stillborn'] }}</b></td></tr></table>
        @if($reproduction['recent_births']->isNotEmpty())<table class="data"><thead><tr><th>Mother</th><th>Date</th><th>Outcome</th><th>Live</th><th>Stillborn</th></tr></thead><tbody>@foreach($reproduction['recent_births'] as $birth)<tr><td><strong>{{ $birth->animal->animal_code }}</strong><br><span class="muted">{{ $birth->animal->species->name }}</span></td><td>{{ $birth->delivered_on->format('M j, Y') }}</td><td>{{ str_replace('_', ' ', ucfirst($birth->outcome)) }}</td><td>{{ $birth->live_count }}</td><td>{{ $birth->stillborn_count }}</td></tr>@endforeach</tbody></table>@endif
    </section>
    @endif

    @if($show('growth'))
    <section class="section"><span class="eyebrow">Growth</span><h2>Weight monitoring</h2>
        <table class="metrics"><tr><td><span class="label">Measurements</span><b>{{ $growth['measurements'] }}</b></td><td><span class="label">Individual records</span><b>{{ $growth['individual_measurements'] }}</b></td><td><span class="label">Batch records</span><b>{{ $growth['batch_measurements'] }}</b></td><td><span class="label">Average weight</span><b>{{ number_format($growth['average_weight'], 2) }} kg</b></td></tr></table>
        @if($growth['recent']->isNotEmpty())<table class="data"><thead><tr><th>Livestock</th><th>Date</th><th>Weight</th><th>Recorded by</th></tr></thead><tbody>@foreach($growth['recent'] as $weight)<tr><td><strong>{{ $weight->animal?->animal_code ?? $weight->batch?->batch_code }}</strong><br><span class="muted">{{ $weight->animal?->species?->name ?? $weight->batch?->species?->name }}</span></td><td>{{ $weight->measured_on->format('M j, Y') }}</td><td>{{ number_format((float) $weight->weight_kg, 2) }} kg</td><td>{{ $weight->recorder->name }}</td></tr>@endforeach</tbody></table>@endif
    </section>
    @endif

    @if($show('reminders'))
    <section class="section"><span class="eyebrow">Follow-ups</span><h2>Pending farm reminders</h2>
        <table class="metrics"><tr><td><span class="label">Pending total</span><b>{{ $reminders['pending'] }}</b></td><td><span class="label">Overdue</span><b class="{{ $reminders['overdue'] ? 'danger' : '' }}">{{ $reminders['overdue'] }}</b></td><td><span class="label">Due in period</span><b>{{ $reminders['due_in_period'] }}</b></td></tr></table>
        @if($reminders['upcoming']->isNotEmpty())<table class="data"><thead><tr><th>Task</th><th>Due</th><th>Livestock</th><th>Priority</th></tr></thead><tbody>@foreach($reminders['upcoming'] as $reminder)<tr><td><strong>{{ $reminder->title }}</strong><br><span class="muted">{{ str_replace('_', ' ', ucfirst($reminder->type)) }}</span></td><td class="{{ $reminder->due_on->isPast() ? 'danger' : '' }}">{{ $reminder->due_on->format('M j, Y') }}</td><td>{{ $reminder->animal?->animal_code ?? $reminder->batch?->batch_code ?? 'Farm-wide' }}</td><td>{{ ucfirst($reminder->priority) }}</td></tr>@endforeach</tbody></table>@else<div class="empty">There are no pending reminders.</div>@endif
    </section>
    @endif
</body>
</html>
