<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Carbon\Carbon;

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
            ->map(function ($n) {
                $i = $n->incident;

                return [
                    'id'       => $n->id,
                    'title'    => $n->title,
                    'message'  => $n->message,
                    'category' => $i->category ?? null,
                    'incident' => $i ? [
                        'id'               => $i->id,
                        'incident_number'  => $i->incident_number,
                        'category'         => $i->category,
                        'status'           => $i->status,
                        'reported_at'      => Carbon::parse($i->reported_at ?? $i->created_at)->format('M d, Y h:i A'),
                        'municipality'     => $i->municipality,
                        'barangay'         => $i->barangay,
                        'landmark'         => $i->landmark,
                        'description'      => $i->description,
                        'reporter_name'    => $i->reporter_name,
                        'reporter_contact' => $i->reporter_contact,
                        'latitude'         => $i->latitude,
                        'longitude'        => $i->longitude,
                    ] : null,
                ];
            });

        return response()->json($notifications);
    }

    public function markRead(Notification $notification)
    {
        abort_unless($notification->user_id === auth()->id(), 403);

        $notification->update(['status' => 'Read']);

        if ($notification->incident && $notification->incident->status === 'Pending') {
            $notification->incident->attemptAutoDispatch();
        }

        return request()->expectsJson() ? response()->json(['ok' => true]) : back();
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

        return request()->expectsJson() ? response()->json(['ok' => true]) : back();
    }
}