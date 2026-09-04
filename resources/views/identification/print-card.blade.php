<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Print {{ $code }} QR card · HerdWise</title>
    <link rel="stylesheet" href="{{ asset('css/herdwise.css') }}">
</head>
<body class="print-card-page">
    <header class="print-toolbar no-print">
        <a class="back" href="{{ $backUrl }}">← Back to profile</a>
        <div><span>Use 100% scale for the clearest scan.</span><button class="btn" type="button" onclick="window.print()">Print card</button></div>
    </header>
    <main class="livestock-print-sheet">
        <section class="livestock-id-card">
            <div class="id-card-heading">
                <div class="scan-brand"><span class="brand-mark">H</span><span>HerdWise<small>{{ $farm->name }}</small></span></div>
                <span class="id-kind">{{ strtoupper($kind) }}</span>
            </div>
            <div class="id-card-body">
                <div class="id-card-details">
                    <span class="eyebrow">LIVESTOCK IDENTIFICATION</span>
                    <h1>{{ $code }}</h1>
                    @if($displayName)<h2>{{ $displayName }}</h2>@endif
                    <p>{{ $species }}{{ $breed ? ' · '.$breed : '' }}</p>
                    <span class="pill {{ str_replace(' ','_',$status) }}">{{ $status }}</span>
                    <dl>
                        @foreach($details as $detail)
                            <div><dt>{{ $detail['label'] }}</dt><dd>{{ $detail['value'] }}</dd></div>
                        @endforeach
                    </dl>
                </div>
                <div class="id-qr">
                    <img src="{{ $qrImageUrl }}" alt="QR code for {{ $code }}">
                    <strong>Scan to open record</strong>
                    <small>Farm team sign-in required</small>
                </div>
            </div>
            <footer><span>{{ $farm->name }}</span><span>Printed {{ today()->format('M j, Y') }}</span></footer>
        </section>
        <p class="print-help no-print">The QR returns an authorized farm member to this livestock profile. No registry or care details are shown before sign-in.</p>
    </main>
</body>
</html>
