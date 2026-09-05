@extends('layouts.app')
@php($isEditing = $animal !== null)
@section('title', ($isEditing ? 'Edit animal' : 'Add animal').' · HerdWise')
@section('heading', $isEditing ? 'Edit individual animal' : 'Add individual animal')

@section('content')
<div class="pagebar">
    <div><span class="eyebrow">{{ $isEditing ? 'CORRECT REGISTRY RECORD' : 'NEW REGISTRY RECORD' }}</span><h2>Animal details</h2><p>{{ $isEditing ? 'Update this profile without losing its health and management history.' : 'Create a searchable profile. Its protected QR card will be ready after saving.' }}</p></div>
</div>
<form class="panel record-form" method="post" action="{{ $isEditing ? route('animals.update', $animal) : route('animals.store') }}">
    @csrf
    @if($isEditing) @method('put') @endif
    @if($errors->any())<div class="errors"><b>Please review the highlighted information.</b><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <div class="form-grid">
        <label>Animal ID *<input name="animal_code" value="{{ old('animal_code', $animal?->animal_code) }}" placeholder="e.g. G-025" required></label>
        <label>Ear tag<input name="ear_tag" value="{{ old('ear_tag', $animal?->ear_tag) }}" placeholder="Existing physical tag"></label>
        <label>Name<input name="name" value="{{ old('name', $animal?->name) }}"></label>
        <label>Species *<select name="species_id" required><option value="">Choose species</option>@foreach($species as $item)@if($item->supports_individuals)<option value="{{ $item->id }}" @selected((string) old('species_id', $animal?->species_id) === (string) $item->id)>{{ $item->name }}</option>@endif @endforeach</select></label>
        <label>Breed<select name="breed_id"><option value="">Not specified</option>@foreach($species as $item)@foreach($item->breeds as $breed)<option value="{{ $breed->id }}" @selected((string) old('breed_id', $animal?->breed_id) === (string) $breed->id)>{{ $item->name }} — {{ $breed->name }}</option>@endforeach @endforeach</select></label>
        <label>Sex *<select name="sex">@foreach(['female' => 'Female', 'male' => 'Male', 'unknown' => 'Unknown'] as $value => $label)<option value="{{ $value }}" @selected(old('sex', $animal?->sex ?? 'female') === $value)>{{ $label }}</option>@endforeach</select></label>
        <label>Birth date<input type="date" name="birth_date" value="{{ old('birth_date', $animal?->birth_date?->toDateString()) }}"></label>
        <label>Status *<select name="status">@foreach(['healthy' => 'Healthy', 'under_observation' => 'Under observation', 'sick' => 'Sick', 'under_treatment' => 'Under treatment', 'pregnant' => 'Pregnant', 'quarantined' => 'Quarantined', 'sold' => 'Sold', 'deceased' => 'Deceased'] as $value => $label)<option value="{{ $value }}" @selected(old('status', $animal?->status ?? 'healthy') === $value)>{{ $label }}</option>@endforeach</select></label>
        <label>Pen / location<input name="location" value="{{ old('location', $animal?->location) }}"></label>
        <label class="span-2">Notes<textarea name="notes" rows="4">{{ old('notes', $animal?->notes) }}</textarea></label>
    </div>
    <div class="form-actions"><a href="{{ $isEditing ? route('animals.show', $animal) : route('animals.index') }}">Cancel</a><button class="btn">{{ $isEditing ? 'Save changes' : 'Save animal' }}</button></div>
</form>
@endsection
