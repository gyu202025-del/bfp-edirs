@extends('layouts.app')
@section('title', 'Dashboard')
@push('styles')<style>#dashboardMap{height:320px;}</style>@endpush

@section('content')

@php
    $statusClass = [
        'Pending' => 'status-pending', 'Dispatched' => 'status-dispatched',
        'Resolved' => 'status-resolved', 'Cancelled' => 'status-cancelled',
    ];
    $dispatchStatusClass = ['Pending' => 'dispatch-pending', 'Completed' => 'dispatch-completed', 'Cancelled' => 'dispatch-cancelled'];
@endphp

<h4 class="fw-semibold mb-1">Welcome, {{ auth()->user()->name }}</h4>
<p class="text-muted mb-4" style="font-size:.85rem;">
    @if ($department)
        {{ $department }} department dashboard — reports for {{ auth()->user()->municipality }}.
    @elseif (auth()->user()->isMunicipalStaff())
        Here's what's happening in {{ auth()->user()->municipality }} right now.
    @else
        Here's what's happening across Leyte Province right now.
    @endif
</p>

<div class="row g-3 mb-4">
    @foreach ($stats as $s)
    <div class="col-6 col-xl-4 col-xxl-2">
        <div class="stat-card">
            <div><div class="stat-value">{{ $s['value'] }}</div><div class="stat-label">{{ $s['label'] }}</div></div>
            <div class="stat-icon" style="background:{{ $s['bg'] }};color:{{ $s['fg'] }};"><i class="bi {{ $s['icon'] }}"></i></div>
        </div>
    </div>
    @endforeach
</div>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card-flat p-3 mb-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="fw-semibold mb-0">Recent Reports</h6>
                <a href="{{ route('incidents.index') }}" class="text-decoration-none" style="font-size:.8rem;">View all</a>
            </div>
            @forelse ($recentIncidents as $i)
                <div class="d-flex justify-content-between align-items-start py-2 border-bottom">
                    <div>
                        <span class="mono d-block" style="font-size:.72rem;">{{ $i->incident_number }}</span>
                        <span class="fw-semibold" style="font-size:.85rem;">{{ $i->category ?? $i->incident_type }}</span>
                        <div class="text-secondary" style="font-size:.75rem;">{{ $i->barangay ?? $i->landmark }}, {{ $i->municipality }}</div>
                    </div>
                    <span class="status-pill {{ $statusClass[$i->status] ?? 'status-pending' }}" style="font-size:.68rem;">{{ $i->status }}</span>
                </div>
            @empty
                <p class="text-secondary text-center py-3" style="font-size:.85rem;">No reports yet.</p>
            @endforelse
        </div>
        <div class="card-flat p-3">
            <h6 class="fw-semibold mb-2">Latest Dispatch</h6>
            @forelse ($latestDispatches as $d)
                <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                    <span style="font-size:.85rem;">{{ $d->station->station_name ?? '—' }}</span>
                    <span class="status-pill {{ $dispatchStatusClass[$d->status] ?? 'dispatch-pending' }}" style="font-size:.68rem;">{{ $d->status }}</span>
                </div>
            @empty
                <p class="text-secondary text-center py-3" style="font-size:.85rem;">No dispatches yet.</p>
            @endforelse
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card-flat p-3">
            <h6 class="fw-semibold mb-2">GIS Map</h6>
            <div id="dashboardMap" style="border-radius:8px;overflow:hidden;"></div>
        </div>
    </div>
</div>
@endsection

@php
    $dashboardMapPoints = $mapIncidents->map(function ($i) {
        return [
            'lat' => $i->latitude,
            'lng' => $i->longitude,
            'status' => strtolower($i->status),
            'category' => $i->category ?? $i->incident_type,
            'label' => $i->incident_number . ' — ' . ($i->category ?? $i->incident_type),
        ];
    });
@endphp

@push('scripts')
<script>
    // Leyte province bounding box only
    const leyteBounds = L.latLngBounds([10.1, 124.3], [11.8, 125.3]);

    const map = L.map('dashboardMap', {
        maxBounds: leyteBounds,
        maxBoundsViscosity: 1.0,
        minZoom: 9
    }).setView([11.2444, 125.0064], 10);

    map.fitBounds(leyteBounds);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);

    const categoryColors = { 'Fire': '#DC2626', 'Medical': '#0891B2', 'Crime/Security': '#1E3A8A', 'Accident': '#F59E0B' };
    const categoryIcons = { 'Fire': '🔥', 'Medical': '🚑', 'Crime/Security': '🚓', 'Accident': '⚠️' };

    function makeIcon(category, status) {
        const bg = categoryColors[category] || '#667085';
        if (status === 'pending') {
            return L.divIcon({ className: '', html: `<div style="width:16px;height:16px;border-radius:50%;background:${bg};border:2px solid #fff;box-shadow:0 2px 6px rgba(0,0,0,.35);"></div>`, iconSize: [16, 16], iconAnchor: [8, 8] });
        }
        const emoji = categoryIcons[category] || '📍';
        return L.divIcon({ className: '', html: `<div style="width:30px;height:30px;border-radius:50%;background:${bg};border:2px solid #fff;display:flex;align-items:center;justify-content:center;font-size:14px;box-shadow:0 2px 6px rgba(0,0,0,.35);">${emoji}</div>`, iconSize: [30, 30], iconAnchor: [15, 15] });
    }

    const points = @json($dashboardMapPoints);
    points.forEach(p => {
        if (!p.lat || !p.lng) return;
        L.marker([p.lat, p.lng], { icon: makeIcon(p.category, p.status) })
            .addTo(map)
            .bindPopup(`<strong>${p.label}</strong>`);
    });
</script>
@endpush