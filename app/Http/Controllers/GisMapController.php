<?php

namespace App\Http\Controllers;

use App\Models\Incident;

class GisMapController extends Controller
{
    public function index()
    {
        return view('gis.map', ['incidents' => $this->scoped()->get()]);
    }

    public function live()
    {
        $incidents = $this->scoped()->get()->map(fn ($i) => [
            'id' => $i->id,
            'lat' => $i->latitude,
            'lng' => $i->longitude,
            'status' => strtolower($i->status),
            'category' => $i->category ?? $i->incident_type,
            'label' => $i->incident_number . ' — ' . ($i->category ?? $i->incident_type) . ($i->landmark ? ', ' . $i->landmark : ''),
            'reported_at' => $i->reported_at?->toIso8601String(),
        ]);

        return response()->json($incidents);
    }

   private function scoped()
{
    $query = Incident::whereNotIn('status', ['Resolved', 'Cancelled'])
        ->whereNotNull('latitude');

    if (auth()->user()->role === 'Head' && auth()->user()->municipality) {
        $municipality = auth()->user()->municipality;
        $query->where(function ($q) use ($municipality) {
            $q->where('municipality', $municipality)
              ->orWhereHas('dispatch.station', fn ($s) => $s->where('municipality', $municipality));
        });
    }

    return $query;
}
}