<?php
 
namespace App\Http\Controllers;
 
use App\Models\Dispatch;
use App\Models\FireStation;
use App\Models\Incident;
use App\Models\IncidentAssignment;
use App\Support\DepartmentRouting;
 
class IncidentController extends Controller
{
    public function index()
    {
        $query = Incident::latest('reported_at');
 
        DepartmentRouting::filterAssignedIncidents($query, auth()->user());

        $incidents = $query->with(['assignments.department'])->get();
        $myAssignment = $incidents->mapWithKeys(function (Incident $incident) {
            return [$incident->id => $incident->assignments->firstWhere('department_id', auth()->user()->department_id)];
        });

        return view('incidents.index', compact('incidents', 'myAssignment'));
    }
 
    // Administrators see all incidents; department accounts need a matching assignment.
    private function authorizeIncident(Incident $incident): void
    {
        $user = auth()->user();
 
        if ($user->isAdministrator()) {
            return;
        }

        abort_unless(
            $user->isMunicipalStaff()
                && $user->department_id
                && $incident->assignments()->where('department_id', $user->department_id)->exists(),
            403
        );
    }

    private function currentAssignment(Incident $incident): IncidentAssignment
    {
        $user = auth()->user();
        abort_unless($user->department_id, 403);

        return $incident->assignments()
            ->where('department_id', $user->department_id)
            ->firstOrFail();
    }

    public function acknowledge(Incident $incident)
    {
        $this->authorizeIncident($incident);
        $assignment = $this->currentAssignment($incident);
        if ($assignment->status === 'Pending') {
            $assignment->update(['status' => 'Acknowledged', 'acknowledged_at' => now()]);
        }
        if (auth()->user()->hasDepartment('BFP') && ($incident->category ?? $incident->incident_type) === 'Fire') {
            $incident->attemptAutoDispatch();
        }

        return back()->with('success', 'Incident #' . $incident->display_number . ' acknowledged.');
    }

    public function verify(Incident $incident)
    {
        return $this->acknowledge($incident);
    }

    public function cancel(Incident $incident)
    {
        $this->authorizeIncident($incident);
 
        if (auth()->user()->isAdministrator()) {
            $incident->update(['status' => 'Cancelled']);
            $incident->assignments()->whereNotIn('status', ['Resolved', 'Cancelled'])->update(['status' => 'Cancelled']);
        } else {
            $assignment = $this->currentAssignment($incident);
            abort_unless(!in_array($assignment->status, ['Resolved', 'Cancelled'], true), 403);
            $assignment->update(['status' => 'Cancelled']);
        }
 
        return redirect()->route('incidents.index')->with('success', 'Incident #' . $incident->display_number . ' cancelled.');
    }
 
    public function resolve(Incident $incident)
    {
        $this->authorizeIncident($incident);
 
        if (auth()->user()->isAdministrator() && $incident->status !== 'Dispatched') {
            return back()->with('error', 'Only dispatched incidents can be marked as resolved.');
        }

        if (auth()->user()->isAdministrator()) {
            Dispatch::where('incident_id', $incident->id)
                ->whereNotIn('status', ['Completed', 'Cancelled'])
                ->update(['status' => 'Completed', 'completion_time' => now()]);
            $incident->assignments()->whereNotIn('status', ['Resolved', 'Cancelled'])->update([
                'status' => 'Resolved',
                'resolved_at' => now(),
            ]);
        } else {
            $assignment = $this->currentAssignment($incident);
            abort_unless($assignment->status === 'Acknowledged', 403);
            $assignment->update(['status' => 'Resolved', 'resolved_at' => now()]);
            if (auth()->user()->hasDepartment('BFP')) {
                Dispatch::where('incident_id', $incident->id)
                    ->whereNotIn('status', ['Completed', 'Cancelled'])
                    ->update(['status' => 'Completed', 'completion_time' => now()]);
            }
        }

        $allDepartmentsDone = !$incident->assignments()->whereNotIn('status', ['Resolved', 'Cancelled'])->exists();
        if ($allDepartmentsDone || auth()->user()->isAdministrator()) {
            $incident->update(['status' => 'Resolved']);
        }
 
        return back()->with('success', 'Incident #' . $incident->display_number . ' marked as resolved.');
    }
 
    public function nearbyStations(Incident $incident)
    {
        $this->authorizeIncident($incident);
        abort_unless(auth()->user()->isAdministrator() || auth()->user()->hasDepartment('BFP'), 403);
 
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