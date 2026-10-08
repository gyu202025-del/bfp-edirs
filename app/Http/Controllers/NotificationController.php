<?php

namespace App\Http\Controllers;

use App\Models\Notification;

class NotificationController extends Controller
{
    public function index()
    {
        $notifications = Notification::where('user_id', auth()->id())
            ->latest('created_at')
            ->get();

        return view('notifications.index', ['notifications' => $notifications]);
    }

    public function liveUnread()
    {
        $notifications = Notification::where('user_id', auth()->id())
            ->where('status', 'Unread')
            ->with('incident')
            ->latest('created_at')
            ->get()
            ->map(fn ($n) => [
                'id' => $n->id,
                'title' => $n->title,
                'message' => $n->message,
                'category' => $n->incident->category ?? null,
            ]);

        return response()->json($notifications);
    }

    public function markRead(Notification $notification)
    {
        abort_unless($notification->user_id === auth()->id(), 403);

        $notification->update(['status' => 'Read']);

        if ($notification->incident && $notification->incident->status === 'Pending') {
            $notification->incident->attemptAutoDispatch();
        }

        return back();
    }

    public function markAllRead()
    {
        $notifications = Notification::where('user_id', auth()->id())
            ->where('status', 'Unread')
            ->with('incident')
            ->get();

        foreach ($notifications as $notification) {
            if ($notification->incident && $notification->incident->status === 'Pending') {
                $notification->incident->attemptAutoDispatch();
            }
        }

        Notification::where('user_id', auth()->id())->update(['status' => 'Read']);

        return back();
    }
}