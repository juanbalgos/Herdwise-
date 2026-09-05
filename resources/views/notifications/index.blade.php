@extends('layouts.app')
@section('title', 'Notifications · HerdWise')
@section('heading', 'Notifications')

@section('content')
<div class="pagebar notification-pagebar">
    <div><span class="eyebrow">FARM ALERTS</span><h2>Care that needs attention.</h2><p>Automatic notices for {{ $farm->name }}, sent before scheduled work is due.</p></div>
    @if($unreadNotificationCount > 0)
        <form method="post" action="{{ route('notifications.read-all') }}">@csrf @method('patch')<button class="outline compact">Mark all as read</button></form>
    @endif
</div>

<section class="panel notification-panel">
    <div class="panel-head"><div><span class="eyebrow">INBOX</span><h3>Reminder notifications</h3></div><span class="muted">{{ $unreadNotificationCount }} unread</span></div>
    <div class="notification-list">
        @forelse($notifications as $notification)
            <article class="notification-row {{ $notification['is_unread'] ? 'unread' : '' }}">
                <span class="notification-symbol">{{ $notification['is_unread'] ? '!' : '✓' }}</span>
                <div>
                    <small>{{ strtoupper($notification['type']) }} · {{ strtoupper($notification['due_label']) }}</small>
                    <h3>{{ $notification['title'] }}</h3>
                    <p>{{ $notification['subject_code'] }} · Scheduled {{ $notification['due_on']->format('M j, Y') }}</p>
                    <time>Sent {{ $notification['created_at']->diffForHumans() }}</time>
                </div>
                <div class="notification-actions">
                    <a class="outline compact" href="{{ $notification['target_url'] }}">Open record</a>
                    @if($notification['is_unread'])
                        <form method="post" action="{{ route('notifications.read', $notification['id']) }}">@csrf @method('patch')<button class="text-button notification-read">Mark read</button></form>
                    @endif
                </div>
            </article>
        @empty
            <div class="empty-state"><span>✓</span><h3>No notifications yet</h3><p>HerdWise will alert the farm team when scheduled care enters its three-day due window.</p></div>
        @endforelse
    </div>
    @if($notifications->hasPages())<div class="notification-pagination">{{ $notifications->links() }}</div>@endif
</section>
@endsection
