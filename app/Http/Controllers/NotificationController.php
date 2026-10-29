<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Carbon\Carbon;

class NotificationController extends Controller
{
    public function index()
    {
        $notifications = $this->visibleNotifications()
            ->latest('created_at')
            ->get();

        return view('notifications.index', ['notifications' => $notifications]);
    }

    public function liveUnread()
    {
        $notifications = $this->visibleNotifications()
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
        abort_unless($this->visibleNotifications()->whereKey($notification->id)->exists(), 403);

        $notification->update(['status' => 'Read']);

        $incident = $notification->incident;
        if ($incident && auth()->user()->department_id) {
            $assignment = $incident->assignments()
                ->where('department_id', auth()->user()->department_id)
                ->first();
            if ($assignment && $assignment->status === 'Pending') {
                $assignment->update(['status' => 'Acknowledged', 'acknowledged_at' => now()]);
                if (auth()->user()->hasDepartment('BFP') && ($incident->category ?? $incident->incident_type) === 'Fire') {
                    $incident->attemptAutoDispatch();
                }
            }
        }

        return request()->expectsJson() ? response()->json(['ok' => true]) : back();
    }

    public function markAllRead()
    {
        $notifications = $this->visibleNotifications()
            ->where('status', 'Unread')
            ->with('incident')
            ->get();

        foreach ($notifications as $notification) {
            $incident = $notification->incident;
            if ($incident && auth()->user()->department_id) {
                $assignment = $incident->assignments()
                    ->where('department_id', auth()->user()->department_id)
                    ->first();
                if ($assignment && $assignment->status === 'Pending') {
                    $assignment->update(['status' => 'Acknowledged', 'acknowledged_at' => now()]);
                    if (auth()->user()->hasDepartment('BFP') && ($incident->category ?? $incident->incident_type) === 'Fire') {
                        $incident->attemptAutoDispatch();
                    }
                }
            }
        }

        Notification::whereIn('id', $notifications->pluck('id'))->update(['status' => 'Read']);

        return request()->expectsJson() ? response()->json(['ok' => true]) : back();
    }

    private function visibleNotifications()
    {
        return Notification::visibleTo(auth()->user());
    }
}