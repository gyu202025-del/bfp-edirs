<?php

namespace App\Http\Controllers;

use App\Models\AssistanceRequest;
use App\Models\Dispatch;
use App\Models\Incident;
use App\Support\DepartmentRouting;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AnalyticsReportController extends Controller
{
    public function index(Request $request)
    {
        $user    = auth()->user();
        $isAdmin = $user->isAdministrator();

        $from = $request->filled('from') ? Carbon::parse($request->from)->startOfDay() : now()->subDays(29)->startOfDay();
        $to   = $request->filled('to')   ? Carbon::parse($request->to)->endOfDay()     : now()->endOfDay();

        $category     = $request->input('category');
        $status       = $request->input('status');
        $municipality = $isAdmin ? $request->input('municipality') : null;

        $incidents = DepartmentRouting::filterAssignedIncidents(Incident::query(), $user)
            ->whereBetween('created_at', [$from, $to])
            ->when($category, fn ($q) => $q->where('category', $category))
            ->when($status, function ($q) use ($isAdmin, $user, $status) {
                if ($isAdmin) {
                    $q->where('status', $status);
                } else {
                    $q->whereHas('assignments', fn ($assignment) => $assignment
                        ->where('department_id', $user->department_id)
                        ->where('status', $status));
                }
            })
            ->when($isAdmin && $municipality, fn ($q) => $q->where('municipality', $municipality))
            ->with(['dispatch.station', 'assignments' => fn ($q) => $q->where('department_id', $user->department_id)])
            ->latest()
            ->get();

        $ids = $incidents->pluck('id');

        // Summary cards
        $statusForIncident = fn ($incident) => $isAdmin
            ? $incident->status
            : ($incident->assignments->first()?->status ?? 'Pending');
        $incidentStatuses = $incidents->mapWithKeys(fn ($incident) => [$incident->id => $statusForIncident($incident)]);
        $summary = [
            'total'      => $incidents->count(),
            'pending'    => $incidentStatuses->filter(fn ($value) => $value === 'Pending')->count(),
            'active'     => $incidentStatuses->filter(fn ($value) => in_array($value, $isAdmin ? ['Dispatched'] : ['Acknowledged'], true))->count(),
            'resolved'   => $incidentStatuses->filter(fn ($value) => $value === 'Resolved')->count(),
            'cancelled'  => $incidentStatuses->filter(fn ($value) => $value === 'Cancelled')->count(),
        ];

        // Charts
        $byCategory = $incidents->groupBy(fn ($i) => $i->category ?: 'Unspecified')->map->count()->sortDesc();
        $byMunicipality = $incidents->groupBy(fn ($i) => $i->municipality ?: 'Unspecified')->map->count()->sortDesc()->take(10);
        $byStatus = $incidentStatuses->groupBy(fn ($value) => $value)->map->count();

        $perMonth = $from->diffInDays($to) > 31;
        $trend = $incidents
            ->groupBy(fn ($i) => $i->created_at->format($perMonth ? 'Y-m' : 'Y-m-d'))
            ->map->count()
            ->sortKeys();

        // Response / dispatch stats
        $dispatches = Dispatch::whereIn('incident_id', $ids)->with('station')->get();

        $perStation = $dispatches
            ->groupBy(fn ($d) => optional($d->station)->station_name ?: 'Unassigned')
            ->map->count()
            ->sortDesc();

        $completed = $dispatches->filter(fn ($d) => $d->completion_time);
        $avgMinutes = $completed->count()
            ? round($completed->avg(fn ($d) => $d->created_at->diffInMinutes($d->completion_time)), 1)
            : null;

        $mutualAid = AssistanceRequest::whereIn('incident_id', $ids)->count();

        $dispatchStats = [
            'total'       => $dispatches->count(),
            'completed'   => $completed->count(),
            'avg_minutes' => $avgMinutes,
            'mutual_aid'  => $mutualAid,
        ];

        $categories = $isAdmin
            ? Incident::whereNotNull('category')->distinct()->pluck('category')
            : collect(DepartmentRouting::categoriesFor($user->department));
        $municipalities = config('leyte.municipalities', []);
        $statuses       = $isAdmin
            ? ['Pending', 'Dispatched', 'Resolved', 'Cancelled']
            : ['Pending', 'Acknowledged', 'Resolved', 'Cancelled'];

        return view('reports.index', compact(
            'incidents', 'summary', 'byCategory', 'byMunicipality', 'byStatus', 'trend',
            'perStation', 'dispatchStats', 'categories', 'municipalities', 'statuses',
            'from', 'to', 'category', 'status', 'municipality', 'isAdmin'
        ));
    }
}