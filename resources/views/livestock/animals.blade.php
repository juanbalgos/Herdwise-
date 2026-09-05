@extends('layouts.app')
@section('title', 'Animals · HerdWise')
@section('heading', 'Individual animals')

@section('content')
<div class="pagebar"><div><span class="eyebrow">LIVESTOCK REGISTRY</span><h2>{{ $animals->total() }} {{ $showArchived ? 'archived' : 'active' }} animal profiles</h2><p>Track goats, cattle, breeding pigs and other individually managed livestock.</p></div>@if($currentFarmRole !== 'veterinarian' && ! $showArchived)<a class="btn" href="{{ route('animals.create') }}">＋ Add animal</a>@endif</div>
<div class="registry-tools">
    <form class="search"><input type="hidden" name="view" value="{{ $showArchived ? 'archived' : 'active' }}"><input name="search" value="{{ request('search') }}" placeholder="Search code, ear tag or name"><button>Search</button></form>
    <div class="view-tabs"><a class="{{ ! $showArchived ? 'active' : '' }}" href="{{ route('animals.index') }}">Active</a><a class="{{ $showArchived ? 'active' : '' }}" href="{{ route('animals.index', ['view' => 'archived']) }}">Archived</a></div>
</div>
<section class="panel cards">@forelse($animals as $animal)<a class="animal-card {{ $animal->archived_at ? 'archived-card' : '' }}" href="{{ route('animals.show', $animal) }}"><div class="animal-symbol">{{ substr($animal->species->name, 0, 1) }}</div><div><span class="pill {{ $animal->status }}">{{ str_replace('_', ' ', $animal->status) }}</span><h3>{{ $animal->name ?: $animal->animal_code }}</h3><p>{{ $animal->animal_code }} · {{ $animal->species->name }}{{ $animal->breed ? ' · '.$animal->breed->name : '' }}</p><small>{{ $animal->archived_at ? 'Archived '.$animal->archived_at->diffForHumans() : ($animal->location ?: 'Location not set') }} →</small></div></a>@empty<div class="empty">{{ $showArchived ? 'No archived animals.' : 'No matching animals found.' }}</div>@endforelse</section>
{{ $animals->links() }}
@endsection
