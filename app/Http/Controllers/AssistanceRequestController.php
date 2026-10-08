<?php

namespace App\Http\Controllers;

use App\Models\Dispatch;
use App\Models\FireStation;
use App\Models\Incident;
use App\Models\Notification;
use App\Models\User;

class AssistanceRequestController extends Controller
{
    public function store(Incident $incident)
    {
        $candidate = FireStation::where('status', 'Active')
            ->where('municipality', '!=', $incident->municipality)
            ->get()
            ->map(fn ($s) => ['station' => $s, 'distance' => $s->distanceTo($incident->latitude, $incident->longitude)])
            ->sortBy('distance')
            ->first();

        if (! $candidate) {
            return back()->with('error', 'No active fire station found in any other municipality.');
        }

        \App\Models\AssistanceRequest::create([
            'incident_id' => $incident->id,
            'requesting_municipality' => $incident->municipality,
            'requested_by' => auth()->id(),
            'target_station_id' => $candidate['station']->id,
            'target_municipality' => $candidate['station']->municipality,
            'status' => 'Approved',
            'responded_by' => auth()->id(),
            'responded_at' => now(),
        ]);

        Dispatch::create([
            'incident_id' => $incident->id,
            'station_id' => $candidate['station']->id,
            'dispatcher_id' => null,
            'dispatch_time' => now(),
            'status' => 'Pending',
        ]);

        $incident->update(['status' => 'Dispatched']);

        // Notify the responding municipality's Heads — their station is now committed
        $targetHeads = User::where('role', 'Head')->where('municipality', $candidate['station']->municipality)->get();
        foreach ($targetHeads as $head) {
            Notification::create([
                'user_id' => $head->id,
                'incident_id' => $incident->id,
                'title' => 'Mutual Aid — Your Station Is Responding',
                'message' => $incident->municipality . ' requested backup — ' . $candidate['station']->station_name . ' is now dispatched to ' . $incident->incident_number . ' (' . $incident->category . ').',
                'status' => 'Unread',
            ]);
        }

        return back()->with('success', 'Mutual aid dispatched — ' . $candidate['station']->station_name . ' (' . $candidate['station']->municipality . ') is responding.');
    }
}