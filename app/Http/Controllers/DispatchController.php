<?php

namespace App\Http\Controllers;

use App\Models\Dispatch;
use App\Models\FireStation;
use App\Models\Incident;
use App\Models\User;
use Illuminate\Http\Request;

class DispatchController extends Controller
{
    public function index()
{
    $municipality = auth()->user()->role === 'Head' ? auth()->user()->municipality : null;

    // "Awaiting Dispatch" now only shows Verified incidents where auto-dispatch
    // failed (e.g. no active station in that municipality) — the normal path
    // skips this column entirely since verify() auto-creates the Dispatch record.
    // Incidents that were acknowledged but couldn't be auto-dispatched
// (no Active station in their municipality) still show up here for manual handling.
$queueQuery = Incident::where('status', 'Pending')->whereDoesntHave('dispatch');
    if ($municipality) { $queueQuery->where('municipality', $municipality); }
    $queue = $queueQuery->get();

    $active = Dispatch::with(['incident', 'station', 'dispatcher'])
        ->whereIn('status', ['Pending', 'Responding'])
        ->when($municipality, fn ($q) => $q->whereHas('incident', fn ($i) => $i->where('municipality', $municipality)))
        ->latest()->get();

    $completed = Dispatch::with(['incident', 'station'])
        ->where('status', 'Completed')
        ->when($municipality, fn ($q) => $q->whereHas('incident', fn ($i) => $i->where('municipality', $municipality)))
        ->latest()->limit(10)->get();

    $stationsQuery = FireStation::where('status', 'Active');
    if ($municipality) { $stationsQuery->where('municipality', $municipality); }
    $stations = $stationsQuery->get();

    $personnelQuery = User::where('role', 'Head');
    if ($municipality) { $personnelQuery->where('municipality', $municipality); }
    $personnel = $personnelQuery->get();

    $incomingRequests = \App\Models\AssistanceRequest::with(['incident', 'requestedBy'])
    ->where('status', 'Pending')
    ->when($municipality, fn ($q) => $q->where('target_municipality', $municipality))
    ->latest()->get();

return view('dispatches.index', compact('queue', 'active', 'completed', 'stations', 'personnel'));
}

    public function store(Request $request)
    {
        $data = $request->validate([
            'incident_id' => 'required|exists:incidents,id',
            'station_id' => 'required|exists:fire_stations,id',
            'dispatcher_id' => 'required|exists:users,id',
            'remarks' => 'nullable|string',
        ]);

        $dispatch = Dispatch::create([
            ...$data,
            'dispatch_time' => now(),
            'status' => 'Responding',
        ]);

        Incident::where('id', $data['incident_id'])->update(['status' => 'Dispatched']);

        return redirect()->route('dispatches.index')->with('success', 'Unit dispatched.');
    }
    
        public function complete(Dispatch $dispatch)
    {
        $dispatch->update(['status' => 'Completed', 'completion_time' => now()]);
        $dispatch->incident()->update(['status' => 'Resolved']);

        return redirect()->route('dispatches.index')->with('success', 'Dispatch marked as completed.');
    }

    public function assignOfficer(Request $request, Dispatch $dispatch)
{
    $request->validate(['dispatcher_id' => 'required|exists:users,id']);

    $dispatch->update([
        'dispatcher_id' => $request->dispatcher_id,
        'status' => 'Responding',
    ]);

    $dispatch->incident()->update(['status' => 'Responding']);

    return redirect()->route('dispatches.index')->with('success', 'Officer assigned — unit is now responding.');
}
}