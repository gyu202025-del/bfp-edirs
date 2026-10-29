<?php

namespace App\Http\Controllers;

use App\Models\Dispatch;
use App\Models\FireStation;
use App\Models\Incident;
use App\Support\DepartmentRouting;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $municipality = $user->isMunicipalStaff() ? $user->municipality : null;
        $department = $user->isMunicipalStaff() ? $user->department : null;

        $incidentsQuery = fn () => Incident::when($municipality, fn ($q) => $q->where('municipality', $municipality))
            ->when($user->isMunicipalStaff(), fn ($q) => DepartmentRouting::filterIncidents($q, $department));
        $dispatchQuery = fn () => Dispatch::whereHas('incident', fn ($q) => $q
            ->when($municipality, fn ($qq) => $qq->where('municipality', $municipality))
            ->when($user->isMunicipalStaff(), fn ($qq) => DepartmentRouting::filterIncidents($qq, $department)));
        $stationsQuery = fn () => FireStation::when($municipality, fn ($q) => $q->where('municipality', $municipality));

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
                'value' => $stationsQuery()->where('status', 'Active')->count(),
                'icon' => 'bi-building-fill', 'bg' => '#FFF3CD', 'fg' => '#8A6D00',
            ],
            [
                'label' => 'Reported Today',
                'value' => $incidentsQuery()->whereDate('reported_at', today())->count(),
                'icon' => 'bi-calendar-check', 'bg' => '#E1F3E5', 'fg' => '#2E7D32',
            ],
            [
                'label' => 'Resolved This Month',
                'value' => $incidentsQuery()->where('status', 'Resolved')->whereMonth('updated_at', now()->month)->count(),
                'icon' => 'bi-graph-up', 'bg' => '#E3E0FA', 'fg' => '#5B4BB8',
            ],
            [
                'label' => 'Pending Reports',
                'value' => $incidentsQuery()->where('status', 'Pending')->count(),
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