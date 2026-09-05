@extends('layouts.app')
@php($isEditing = $batch !== null)
@section('title', ($isEditing ? 'Edit batch' : 'Add batch').' · HerdWise')
@section('heading', $isEditing ? 'Edit livestock batch' : 'Add livestock batch')

@section('content')
<div class="pagebar">
    <div><span class="eyebrow">{{ $isEditing ? 'CORRECT GROUP RECORD' : 'NEW GROUP RECORD' }}</span><h2>Batch details</h2><p>{{ $isEditing ? 'Update counts or details while keeping the batch’s complete history.' : 'Use one record when animals are managed collectively.' }}</p></div>
</div>
<form class="panel record-form" method="post" action="{{ $isEditing ? route('batches.update', $batch) : route('batches.store') }}">
    @csrf
    @if($isEditing) @method('put') @endif
    @if($errors->any())<div class="errors"><b>Please review the highlighted information.</b><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <div class="form-grid">
        <label>Batch ID *<input name="batch_code" value="{{ old('batch_code', $batch?->batch_code) }}" placeholder="e.g. B-2026-004" required></label>
        <label>Species *<select name="species_id" required><option value="">Choose species</option>@foreach($species as $item)@if($item->supports_batches)<option value="{{ $item->id }}" @selected((string) old('species_id', $batch?->species_id) === (string) $item->id)>{{ $item->name }}</option>@endif @endforeach</select></label>
        <label>Breed / type<select name="breed_id"><option value="">Not specified</option>@foreach($species as $item)@foreach($item->breeds as $breed)<option value="{{ $breed->id }}" @selected((string) old('breed_id', $batch?->breed_id) === (string) $breed->id)>{{ $item->name }} — {{ $breed->name }}</option>@endforeach @endforeach</select></label>
        <label>Date started *<input type="date" name="started_on" value="{{ old('started_on', $batch?->started_on?->toDateString() ?? now()->toDateString()) }}" required></label>
        <label>Initial count *<input type="number" min="1" max="1000000" name="initial_count" value="{{ old('initial_count', $batch?->initial_count) }}" required></label>
        <label>Current count *<input type="number" min="0" name="current_count" value="{{ old('current_count', $batch?->current_count) }}" required></label>
        <label>Purpose<input name="purpose" value="{{ old('purpose', $batch?->purpose) }}" placeholder="Meat, breeding, layers..."></label>
        <label>Status<select name="status">@foreach(['active' => 'Active', 'under_observation' => 'Under observation', 'quarantined' => 'Quarantined', 'closed' => 'Closed'] as $value => $label)<option value="{{ $value }}" @selected(old('status', $batch?->status ?? 'active') === $value)>{{ $label }}</option>@endforeach</select></label>
        <label>Pen / location<input name="location" value="{{ old('location', $batch?->location) }}"></label>
        <label class="span-2">Notes<textarea name="notes" rows="4">{{ old('notes', $batch?->notes) }}</textarea></label>
    </div>
    <div class="form-actions"><a href="{{ $isEditing ? route('batches.show', $batch) : route('batches.index') }}">Cancel</a><button class="btn">{{ $isEditing ? 'Save changes' : 'Save batch' }}</button></div>
</form>
@endsection
