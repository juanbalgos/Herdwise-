<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $code }} · HerdWise</title>
    <link rel="stylesheet" href="{{ asset('css/herdwise.css') }}">
    <link rel="stylesheet" href="{{ asset('css/public-identification.css') }}">
</head>
<body class="public-profile-page">
    <main class="public-profile-card">
        <div class="public-profile-heading"><div class="scan-brand"><span class="brand-mark">H</span><span>HerdWise<small>{{ $farm->name }}</small></span></div><span class="id-kind">{{ strtoupper($kind) }}</span></div>
        <section class="public-profile-body">
            <span class="eyebrow">LIVESTOCK IDENTIFICATION</span>
            <h1>{{ $code }}</h1>
            @if($displayName)<h2>{{ $displayName }}</h2>@endif
            <p>{{ $species }}{{ $breed ? ' · '.$breed : '' }}</p>
            <dl>@foreach($details as $detail)<div><dt>{{ $detail['label'] }}</dt><dd>{{ $detail['value'] }}</dd></div>@endforeach</dl>
        </section>
        <footer><span>Public identification only</span><span>For full care records, sign in to HerdWise.</span></footer>
    </main>
</body>
</html>
