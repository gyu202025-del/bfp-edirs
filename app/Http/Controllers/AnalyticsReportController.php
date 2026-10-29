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
        $isAdmin = $user->role === 'Administrator';

        $from = $request->filled('from') ? Carbon::parse($request->from)->startOfDay() : now()->subDays(29)->startOfDay();
        $to   = $request->filled('to')   ? Carbon::parse($request->to)->endOfDay()     : now()->endOfDay();

        $category     = $request->input('category');
        $status       = $request->input('status');
        $municipality = $isAdmin ? $request->input('municipality') : $user->municipality;

        $incidents = Incident::query()
            ->whereBetween('created_at', [$from, $to])
            ->when($category, fn ($q) => $q->where('category', $category))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when(!$isAdmin, function ($q) use ($user) {
                $q->where(function ($w) use ($user) {
                    $w->where('municipality', $user->municipality)
                      ->orWhereHas('dispatch.station', fn ($s) => $s->where('municipality', $user->municipality));
                });
                DepartmentRouting::filterIncidents($q, $user->department);
            })
            ->when($isAdmin && $municipality, fn ($q) => $q->where('municipality', $municipality))
            ->with('dispatch.station')
            ->latest()
            ->get();

        $ids = $incidents->pluck('id');

        // Summary cards
        $summary = [
            'total'      => $incidents->count(),
            'pending'    => $incidents->where('status', 'Pending')->count(),
            'active'     => $incidents->where('status', 'Dispatched')->count(),
            'resolved'   => $incidents->where('status', 'Resolved')->count(),
            'cancelled'  => $incidents->where('status', 'Cancelled')->count(),
        ];

        // Charts
        $byCategory = $incidents->groupBy(fn ($i) => $i->category ?: 'Unspecified')->map->count()->sortDesc();
        $byMunicipality = $incidents->groupBy(fn ($i) => $i->municipality ?: 'Unspecified')->map->count()->sortDesc()->take(10);
        $byStatus = $incidents->groupBy('status')->map->count();

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
        $statuses       = ['Pending', 'Dispatched', 'Resolved', 'Cancelled'];

        return view('reports.index', compact(
            'incidents', 'summary', 'byCategory', 'byMunicipality', 'byStatus', 'trend',
            'perStation', 'dispatchStats', 'categories', 'municipalities', 'statuses',
            'from', 'to', 'category', 'status', 'municipality', 'isAdmin'
        ));
    }
}