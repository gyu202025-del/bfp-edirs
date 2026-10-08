<?php

namespace App\Http\Controllers;

use App\Models\Incident;
use App\Models\Dispatch;

class IncidentController extends Controller
{
    public function index()
{
    $query = Incident::latest('reported_at');

    if (auth()->user()->role === 'Head' && auth()->user()->municipality) {
        $municipality = auth()->user()->municipality;
        $query->where(function ($q) use ($municipality) {
            $q->where('municipality', $municipality)
              ->orWhereHas('dispatch.station', fn ($s) => $s->where('municipality', $municipality));
        });
    }

    return view('incidents.index', ['incidents' => $query->get()]);
}

    public function verify(Incident $incident)
{
    $incident->update(['status' => 'Verified', 'verified_at' => now()]);

    $message = $incident->incident_number . ' marked as Verified.';

    if ($incident->latitude && $incident->longitude) {
        $stations = \App\Models\FireStation::where('municipality', $incident->municipality)
            ->where('status', 'Active')
            ->get();

        $nearest = $stations
            ->map(fn ($s) => ['station' => $s, 'distance' => $s->distanceTo($incident->latitude, $incident->longitude)])
            ->sortBy('distance')
            ->first();

        if ($nearest) {
            \App\Models\Dispatch::create([
                'incident_id' => $incident->id,
                'station_id' => $nearest['station']->id,
                'dispatcher_id' => null, // assigned manually later by a Head
                'dispatch_time' => now(),
                'status' => 'Pending',
            ]);

            $incident->update(['status' => 'Dispatched']);

            $message .= ' Auto-dispatched to ' . $nearest['station']->station_name
                . ' (' . round($nearest['distance'], 1) . ' km away).';
        } else {
            $message .= ' No active fire station found in ' . $incident->municipality . ' — dispatch it manually.';
        }
    }

    return redirect()->route('incidents.index')->with('success', $message);
}

    public function cancel(Incident $incident)
    {
        $incident->update(['status' => 'Cancelled']);

        return redirect()->route('incidents.index')->with('success', $incident->incident_number . ' cancelled.');
    }

    public function nearbyStations(Incident $incident)
{
    if (!$incident->latitude || !$incident->longitude) {
        return response()->json([]);
    }

    $stations = \App\Models\FireStation::where('status', 'Active')->get();

    $result = $stations->map(function ($station) use ($incident) {
        $km = $station->distanceTo($incident->latitude, $incident->longitude);
        return [
            'station_name' => $station->station_name,
            'municipality' => $station->municipality,
            'is_home' => $station->municipality === $incident->municipality,
            'km' => round($km, 1),
            'mi' => round($km * 0.621371, 1),
        ];
    })->sortBy('km')->values();

    return response()->json($result);
}
}