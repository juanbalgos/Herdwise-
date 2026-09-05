@extends('layouts.app')
@section('title', 'Batches · HerdWise')
@section('heading', 'Livestock batches')

@section('content')
<div class="pagebar"><div><span class="eyebrow">GROUP MONITORING</span><h2>{{ $batches->total() }} {{ $showArchived ? 'archived' : 'active' }} batch records</h2><p>Practical group tracking for poultry, piglets and fattening groups.</p></div>@if($currentFarmRole !== 'veterinarian' && ! $showArchived)<a class="btn" href="{{ route('batches.create') }}">＋ Add batch</a>@endif</div>
<div class="registry-tools">
    <form class="search"><input type="hidden" name="view" value="{{ $showArchived ? 'archived' : 'active' }}"><input name="search" value="{{ request('search') }}" placeholder="Search batch ID"><button>Search</button></form>
    <div class="view-tabs"><a class="{{ ! $showArchived ? 'active' : '' }}" href="{{ route('batches.index') }}">Active</a><a class="{{ $showArchived ? 'active' : '' }}" href="{{ route('batches.index', ['view' => 'archived']) }}">Archived</a></div>
</div>
<section class="panel cards">@forelse($batches as $batch)<a class="animal-card {{ $batch->archived_at ? 'archived-card' : '' }}" href="{{ route('batches.show', $batch) }}"><div class="animal-symbol batch">{{ substr($batch->species->name, 0, 1) }}</div><div><span class="pill {{ $batch->status }}">{{ str_replace('_', ' ', $batch->status) }}</span><h3>{{ $batch->batch_code }}</h3><p>{{ $batch->species->name }}{{ $batch->breed ? ' · '.$batch->breed->name : '' }}</p><small>{{ $batch->archived_at ? 'Archived '.$batch->archived_at->diffForHumans() : number_format($batch->current_count).' of '.number_format($batch->initial_count).' remaining · '.($batch->location ?: 'Location not set') }}</small></div></a>@empty<div class="empty">{{ $showArchived ? 'No archived batches.' : 'No matching batches found.' }}</div>@endforelse</section>
{{ $batches->links() }}
@endsection
