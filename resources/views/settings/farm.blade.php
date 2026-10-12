@extends('layouts.app')
@section('title', 'Farm settings · HerdWise')
@section('heading', 'Farm settings')

@section('content')
<div class="pagebar"><div><span class="eyebrow">FARM PROFILE</span><h2>Keep {{ $farm->name }} accurate.</h2><p>These details appear throughout your farm workspace and reports.</p></div></div>

<section class="panel record-form">
    <span class="eyebrow">OWNER CONTROLS</span>
    <h3 class="form-title">Farm details</h3>
    <p class="muted">Only the farm owner can update these settings. Livestock records and team access are unchanged.</p>

    <form method="post" action="{{ route('settings.farm.update') }}">
        @csrf
        @method('patch')
        @if($errors->any())<div class="errors"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <div class="form-grid">
            <label class="span-2">Farm name<input name="name" value="{{ old('name', $farm->name) }}" maxlength="255" required></label>
            <label>Location <span class="field-note">Optional</span><input name="location" value="{{ old('location', $farm->location) }}" maxlength="255" placeholder="City or municipality"></label>
            <label>Contact number <span class="field-note">Optional</span><input name="contact_number" value="{{ old('contact_number', $farm->contact_number) }}" maxlength="50" placeholder="e.g. +63 900 000 0000"></label>
            <label class="span-2">Farm timezone<select name="timezone" required>@foreach(['Asia/Manila', 'Asia/Singapore', 'Asia/Bangkok', 'Asia/Jakarta', 'Asia/Tokyo', 'Australia/Perth', 'Australia/Sydney', 'UTC'] as $timezone)<option value="{{ $timezone }}" @selected(old('timezone', $farm->timezone) === $timezone)>{{ $timezone }}</option>@endforeach</select><span class="field-note">Used to calculate reminder dates and timestamps.</span></label>
        </div>
        <div class="form-actions"><a class="back" href="{{ route('dashboard') }}">Cancel</a><button class="btn">Save farm settings</button></div>
    </form>
</section>
@endsection
