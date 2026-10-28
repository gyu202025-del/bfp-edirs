<?php

namespace App\Http\Controllers;

use App\Models\Dispatch;
use App\Models\FireStation;
use App\Models\Incident;

class IncidentController extends Controller
{
    public function index()
    {
        $query = Incident::latest('reported_at');

        if (auth()->user()->isMunicipalStaff() && auth()->user()->municipality) {
            $municipality = auth()->user()->municipality;
            $query->where(function ($q) use ($municipality) {
                $q->where('municipality', $municipality)
                  ->orWhereHas('dispatch.station', fn ($s) => $s->where('municipality', $municipality));
            });
        }

        return view('incidents.index', ['incidents' => $query->get()]);
    }

    // Administrator: any incident. Head/Staff: own municipality, or one their station is responding to.
    private function authorizeIncident(Incident $incident): void
    {
        $user = auth()->user();

        if ($user->role === 'Administrator') {
            return;
        }

        $own    = $incident->municipality === $user->municipality;
        $backup = optional(optional($incident->dispatch)->station)->municipality === $user->municipality;

        abort_unless($own || $backup, 403);
    }

    public function cancel(Incident $incident)
    {
        $this->authorizeIncident($incident);

        $incident->update(['status' => 'Cancelled']);

        return redirect()->route('incidents.index')->with('success', $incident->incident_number . ' cancelled.');
    }

    public function resolve(Incident $incident)
    {
        $this->authorizeIncident($incident);

        if ($incident->status !== 'Dispatched') {
            return back()->with('error', 'Only dispatched incidents can be marked as resolved.');
        }

        Dispatch::where('incident_id', $incident->id)
            ->whereNotIn('status', ['Completed', 'Cancelled'])
            ->update(['status' => 'Completed', 'completion_time' => now()]);

        $incident->update(['status' => 'Resolved']);

        return back()->with('success', 'Incident ' . $incident->incident_number . ' marked as resolved.');
    }

    public function nearbyStations(Incident $incident)
    {
        $this->authorizeIncident($incident);

        if (!$incident->latitude || !$incident->longitude) {
            return response()->json([]);
        }

        $stations = FireStation::where('status', 'Active')->get();

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