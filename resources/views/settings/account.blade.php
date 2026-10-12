@extends('layouts.app')
@section('title', 'My account · HerdWise')
@section('heading', 'My account')

@section('content')
<div class="pagebar"><div><span class="eyebrow">PERSONAL SETTINGS</span><h2>Manage your HerdWise account.</h2><p>Your email is used as your sign-in. Email changes are not included in this capstone version.</p></div></div>

<div class="team-grid">
    <section class="panel record-form">
        <span class="eyebrow">PROFILE</span>
        <h3 class="form-title">Your details</h3>
        <form method="post" action="{{ route('settings.account.update') }}">
            @csrf
            @method('patch')
            @if($errors->has('name'))<div class="errors">{{ $errors->first('name') }}</div>@endif
            <label>Display name<input name="name" value="{{ old('name', $user->name) }}" maxlength="100" required></label>
            <label>Email address<input value="{{ $user->email }}" readonly aria-label="Email address"></label>
            <div class="form-actions"><button class="btn">Save details</button></div>
        </form>
    </section>

    <section class="panel record-form">
        <span class="eyebrow">SECURITY</span>
        <h3 class="form-title">Change password</h3>
        <form method="post" action="{{ route('settings.account.password.update') }}">
            @csrf
            @method('patch')
            @if($errors->hasAny(['current_password', 'password']))<div class="errors"><ul>@foreach($errors->get('current_password') as $error)<li>{{ $error }}</li>@endforeach @foreach($errors->get('password') as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            <label>Current password<input type="password" name="current_password" autocomplete="current-password" required></label>
            <label>New password<input type="password" name="password" autocomplete="new-password" minlength="8" required></label>
            <label>Confirm new password<input type="password" name="password_confirmation" autocomplete="new-password" minlength="8" required></label>
            <div class="form-actions"><button class="btn">Update password</button></div>
        </form>
    </section>
</div>
@endsection
