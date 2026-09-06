<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>@yield('title', 'HerdWise')</title>
    <link rel="stylesheet" href="{{ asset('css/herdwise.css') }}">
    <script defer src="{{ asset('js/herdwise.js') }}"></script>
</head>
<body>
<div class="shell">
    <aside>
        <a class="brand" href="{{ route('dashboard') }}"><span class="brand-mark">H</span><span>HerdWise<small>Farm intelligence</small></span></a>
        <nav>
            <a class="{{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">▦ <span>Dashboard</span></a>
            <p>MANAGEMENT</p>
            <a class="{{ request()->routeIs('animals.*') ? 'active' : '' }}" href="{{ route('animals.index') }}">◉ <span>Individual animals</span></a>
            <a class="{{ request()->routeIs('batches.*') ? 'active' : '' }}" href="{{ route('batches.index') }}">▤ <span>Batches</span></a>
            <a class="{{ request()->routeIs('activities.*') || request()->routeIs('reminders.*') ? 'active' : '' }}" href="{{ route('activities.index') }}">♡ <span>Care & activities</span></a>
            <a class="{{ request()->routeIs('reproduction.*') ? 'active' : '' }}" href="{{ route('reproduction.index') }}">♧ <span>Breeding</span></a>
            <a class="{{ request()->routeIs('weights.*') ? 'active' : '' }}" href="{{ route('weights.index') }}">⌁ <span>Growth & weight</span></a>
            @if($currentFarmRole === 'owner')
                <a class="{{ request()->routeIs('team.*') ? 'active' : '' }}" href="{{ route('team.index') }}">♙ <span>Farm team</span></a>
            @endif
            <p>INSIGHTS</p>
            <a class="{{ request()->routeIs('reports.*') ? 'active' : '' }}" href="{{ route('reports.index') }}">◇ <span>Reports</span></a>
            <a class="{{ request()->routeIs('assistant.*') ? 'active' : '' }}" href="{{ route('assistant.index') }}">✦ <span>Record assistant</span></a>
        </nav>
        <div class="account">
            <span>{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
            <div><b>{{ auth()->user()->name }}</b><small>{{ $currentFarmRole ?? 'Member' }}</small></div>
            <form method="post" action="{{ route('logout') }}">@csrf<button title="Sign out">↪</button></form>
        </div>
    </aside>
    <main>
        <header>
            <div>
                @if($availableFarms->count() > 1)
                    <form class="farm-switcher" method="post" action="{{ route('farms.context.update') }}">
                        @csrf
                        <select name="farm_id" aria-label="Current farm">
                            @foreach($availableFarms as $farmOption)
                                <option value="{{ $farmOption->id }}" @selected($currentFarm?->id === $farmOption->id)>{{ $farmOption->name }} · {{ ucfirst($farmOption->pivot->role) }}</option>
                            @endforeach
                        </select>
                        <button>Switch</button>
                    </form>
                @else
                    <small>{{ $currentFarm?->name ?? 'No active farm' }}</small>
                @endif
                <h1>@yield('heading')</h1>
            </div>
            <a class="icon-btn notification-button {{ request()->routeIs('notifications.*') ? 'active' : '' }}" href="{{ route('notifications.index') }}" title="Notifications" aria-label="{{ $unreadNotificationCount }} unread reminder {{ str('notification')->plural($unreadNotificationCount) }}">
                ♢
                @if($unreadNotificationCount > 0)<span>{{ min($unreadNotificationCount, 99) }}</span>@endif
            </a>
        </header>
        <section class="content">
            @if(session('success'))<div class="flash">✓ {{ session('success') }}</div>@endif
            @yield('content')
        </section>
    </main>
</div>
</body>
</html>
