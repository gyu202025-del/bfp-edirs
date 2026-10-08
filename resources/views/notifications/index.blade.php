@extends('layouts.app')

@section('title', 'Notifications')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h6 class="fw-semibold mb-0">Notifications</h6>
    <form method="POST" action="{{ route('notifications.markAllRead') }}">
        @csrf
        <button type="submit" class="btn btn-sm btn-outline-secondary">Mark all as read</button>
    </form>
</div>

<div class="card-flat p-0">
    @forelse ($notifications as $n)
        <div class="d-flex justify-content-between align-items-start p-3" style="border-bottom: 1px solid var(--border, #E4E7EC); {{ $n->status === 'Unread' ? 'background:#FBFBFD;' : '' }}">
            <div class="d-flex gap-2">
                <span>{{ $n->status === 'Unread' ? '🔴' : '⚪' }}</span>
                <div>
                    <div class="fw-semibold" style="font-size:.88rem;">{{ $n->title }}</div>
                    <div class="text-secondary" style="font-size:.82rem;">{{ $n->message }}</div>
                    <div class="text-secondary" style="font-size:.72rem;">{{ $n->created_at->diffForHumans() }}</div>
                </div>
            </div>
            @if ($n->status === 'Unread')
                <form method="POST" action="{{ route('notifications.markRead', $n->id) }}">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-secondary">Mark read</button>
                </form>
            @endif
        </div>
    @empty
        <div class="text-center text-secondary py-5">No notifications yet.</div>
    @endforelse
</div>
@endsection