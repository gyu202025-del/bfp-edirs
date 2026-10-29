<?php

namespace App\Http\Controllers;

use App\Models\Dispatch;
use App\Models\FireStation;
use App\Models\Incident;
use App\Models\IncidentAssignment;
use App\Support\DepartmentRouting;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $department = $user->isMunicipalStaff() ? $user->department : null;

        $incidentsQuery = fn () => DepartmentRouting::filterAssignedIncidents(Incident::query(), $user);
        $dispatchQuery = fn () => Dispatch::whereHas('incident', fn ($q) => DepartmentRouting::filterAssignedIncidents($q, $user));
        $stationsQuery = fn () => FireStation::query();

        $stats = [
            [
                'label' => 'Total Incidents',
                'value' => $incidentsQuery()->count(),
                'icon' => 'bi-fire', 'bg' => '#FDE0DE', 'fg' => '#C62828',
            ],
            [
                'label' => 'Active Dispatch',
                'value' => $dispatchQuery()->where('status', 'Pending')->count(),
                'icon' => 'bi-truck', 'bg' => '#DCEBFF', 'fg' => '#1565C0',
            ],
            [
                'label' => 'Fire Stations',
                'value' => $user->isAdministrator() || $user->hasDepartment('BFP')
                    ? $stationsQuery()->where('status', 'Active')->count()
                    : 0,
                'icon' => 'bi-building-fill', 'bg' => '#FFF3CD', 'fg' => '#8A6D00',
            ],
            [
                'label' => 'Reported Today',
                'value' => $incidentsQuery()->whereDate('reported_at', today())->count(),
                'icon' => 'bi-calendar-check', 'bg' => '#E1F3E5', 'fg' => '#2E7D32',
            ],
            [
                'label' => 'Resolved This Month',
                'value' => $user->isAdministrator()
                    ? $incidentsQuery()->where('status', 'Resolved')->whereMonth('updated_at', now()->month)->count()
                    : IncidentAssignment::where('department_id', $user->department_id)->where('status', 'Resolved')->whereMonth('resolved_at', now()->month)->count(),
                'icon' => 'bi-graph-up', 'bg' => '#E3E0FA', 'fg' => '#5B4BB8',
            ],
            [
                'label' => 'Pending Reports',
                'value' => $user->isAdministrator()
                    ? $incidentsQuery()->where('status', 'Pending')->count()
                    : IncidentAssignment::where('department_id', $user->department_id)->where('status', 'Pending')->count(),
                'icon' => 'bi-hourglass-split', 'bg' => '#F3E6E6', 'fg' => '#E53935',
            ],
        ];

        $recentIncidents = $incidentsQuery()->latest('reported_at')->limit(5)->get();

        $latestDispatches = $dispatchQuery()->with(['incident', 'station'])->latest('dispatch_time')->limit(3)->get();

        $mapIncidents = $incidentsQuery()
            ->whereNotIn('status', ['Resolved', 'Cancelled'])
            ->whereNotNull('latitude')
            ->limit(50)
            ->get();

        return view('dashboard.index', compact('stats', 'recentIncidents', 'latestDispatches', 'mapIncidents', 'department'));
    }
}