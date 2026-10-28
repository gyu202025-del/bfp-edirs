@extends('layouts.app')

@section('title', 'Live GIS Map')

@push('styles')
<style>
    #liveMap { height: calc(100vh - 64px - 3rem - 60px); min-height: 480px; }
    .map-shell { position: relative; }
    .map-shell:fullscreen #liveMap { height: 100vh; }
    .map-shell:fullscreen { padding: 0 !important; border-radius: 0 !important; }
    .map-filter-bar {
        position: absolute; top: 16px; left: 16px; z-index: 500;
        background: var(--card-bg, #fff);
        border: 1px solid var(--border, #E4E7EC);
        border-radius: var(--radius, 10px);
        box-shadow: var(--shadow, 0 2px 8px rgba(27,38,59,.08));
        padding: .6rem .8rem;
        display: flex; gap: .5rem; flex-wrap: wrap; align-items: center; max-width: calc(100% - 260px);
    }
    .map-legend-box { position: absolute; bottom: 16px; left: 16px; z-index: 500; }
    .map-legend {
        background: var(--card-bg, #fff);
        border: 1px solid var(--border, #E4E7EC);
        border-radius: 8px;
        padding: .6rem .8rem;
        font-size: .78rem;
    }
    .legend-swatch { width: 10px; height: 10px; border-radius: 50%; display: inline-block; margin-right: .3rem; }
    .live-toast-stack {
        position: fixed; top: 80px; right: 20px; z-index: 2000;
        display: flex; flex-direction: column; gap: .5rem;
    }
    .today-toggle-label { font-size: .82rem; display: flex; align-items: center; gap: .4rem; margin: 0; cursor: pointer; }
    .fullscreen-btn {
        position: absolute; top: 16px; right: 16px; z-index: 500;
        background: var(--card-bg, #fff);
        border: 1px solid var(--border, #E4E7EC);
        border-radius: var(--radius, 10px);
        box-shadow: var(--shadow, 0 2px 8px rgba(27,38,59,.08));
        width: 38px; height: 38px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1rem; cursor: pointer;
    }
    .fullscreen-btn:hover { background: #F5F5F5; }
    .popup-report-date { font-size: .75rem; color: #667085; margin-top: 2px; }

    @keyframes pending-pulse-ring {
        0%   { transform: scale(1);   opacity: .7; }
        100% { transform: scale(2.8); opacity: 0; }
    }
    .pending-marker-wrap {
        position: relative;
        width: 16px;
        height: 16px;
    }
    .pending-marker-dot {
        position: absolute;
        inset: 0;
        border-radius: 50%;
        border: 2px solid #fff;
        box-shadow: 0 2px 6px rgba(0,0,0,.35);
        z-index: 2;
    }
    .pending-marker-ring {
        position: absolute;
        inset: 0;
        border-radius: 50%;
        border: 2px solid currentColor;
        animation: pending-pulse-ring 1.6s ease-out infinite;
        z-index: 1;
    }
</style>
@endpush

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h6 class="fw-semibold mb-0">Live GIS Map</h6>
        <div class="text-secondary" style="font-size:.82rem;">Real-time incident locations across Leyte Province</div>
    </div>
    <div class="d-flex align-items-center gap-2">
        <span class="status-pill status-responding" id="liveIndicator">
            <span class="status-dot live" style="background:currentColor;"></span> Live
        </span>
        <span class="text-secondary" id="lastUpdated" style="font-size:.75rem;"></span>
    </div>
</div>

<div class="card-flat p-2 map-shell" id="mapShell">
    <div class="map-filter-bar">
        <select class="form-select form-select-sm" style="width:auto;" id="statusFilter">
            <option value="">All statuses</option>
            <option>Pending</option><option>Verified</option><option>Dispatched</option>
            <option>Responding</option><option>Resolved</option><option>Cancelled</option>
        </select>
        <select class="form-select form-select-sm" style="width:auto;" id="categoryFilter">
            <option value="">All categories</option>
            <option>Fire</option><option>Medical</option><option>Crime/Security</option><option>Accident</option>
        </select>
        <label class="today-toggle-label">
            <input type="checkbox" class="form-check-input m-0" id="todayToggle" checked>
            Today only
        </label>
        <label class="today-toggle-label">
            <input type="checkbox" class="form-check-input m-0" id="showReportsToggle" checked>
            Show reports
        </label>
    </div>

    <button class="fullscreen-btn" id="fullscreenBtn" title="Toggle fullscreen">
        <i class="bi bi-arrows-fullscreen" id="fullscreenIcon"></i>
    </button>

    <div id="liveMap" class="gis-map"></div>

    <div class="map-legend-box map-legend">
        <div class="fw-semibold mb-1" style="font-size:.75rem;">Category color</div>
        <div class="d-flex flex-column gap-1 mb-2">
            <div><span class="legend-swatch" style="background:#DC2626;"></span>🔥 Fire</div>
            <div><span class="legend-swatch" style="background:#0891B2;"></span>🚑 Medical</div>
            <div><span class="legend-swatch" style="background:#1E3A8A;"></span>🚓 Crime/Security</div>
            <div><span class="legend-swatch" style="background:#F59E0B;"></span>⚠️ Accident</div>
        </div>
        <div class="fw-semibold mb-1" style="font-size:.75rem;">Marker stage</div>
        <div class="d-flex flex-column gap-1">
            <div><span class="legend-swatch" style="background:#888;"></span> Pending — pulsing dot</div>
            <div>🔥 Verified onward — icon shown</div>
        </div>
    </div>
</div>

{{-- Toast stack for new-incident notifications --}}
<div class="live-toast-stack" id="toastStack"></div>

@endsection

@push('scripts')
<script>
    // Leyte province bounding box only (not the whole Region 8) —
    // covers Tacloban City, Palo, Ormoc, Baybay, Carigara, and everywhere between
    const leyteBounds = L.latLngBounds(
        [10.1, 124.3],  // Southwest corner
        [11.8, 125.3]   // Northeast corner
    );

    const liveMap = L.map('liveMap', {
        maxBounds: leyteBounds,
        maxBoundsViscosity: 1.0, // "sticky" edges — can't drag past the boundary
        minZoom: 9                // can't zoom out far enough to leave the province
    }).setView([11.2444, 125.0064], 10);

    liveMap.fitBounds(leyteBounds);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(liveMap);

    // ---------- Fullscreen toggle ----------
    const mapShell = document.getElementById('mapShell');
    const fullscreenBtn = document.getElementById('fullscreenBtn');
    const fullscreenIcon = document.getElementById('fullscreenIcon');

    fullscreenBtn.addEventListener('click', function () {
        if (!document.fullscreenElement) {
            mapShell.requestFullscreen().catch(() => {});
        } else {
            document.exitFullscreen();
        }
    });

    document.addEventListener('fullscreenchange', function () {
        const isFullscreen = !!document.fullscreenElement;
        fullscreenIcon.className = isFullscreen ? 'bi bi-fullscreen-exit' : 'bi bi-arrows-fullscreen';
        setTimeout(() => liveMap.invalidateSize(), 150);
    });

    // Fixed color per CATEGORY (not status) — Fire is always red, etc.
    const categoryColors = {
        'Fire': '#DC2626',
        'Medical': '#0891B2',
        'Crime/Security': '#1E3A8A',
        'Accident': '#F59E0B',
    };
    const categoryIcons = {
        'Fire': '🔥',
        'Medical': '🚑',
        'Crime/Security': '🚓',
        'Accident': '⚠️',
    };
    const statusColors = { pending: '#B7791E', verified: '#6941C6', dispatched: '#175CD3', responding: '#B3251A', resolved: '#067647', cancelled: '#667085' };

    function makeIcon(category, status) {
        const bg = categoryColors[category] || '#667085';
        const isPending = status === 'pending';

        if (isPending) {
            return L.divIcon({
                className: '',
                html: `
                    <div class="pending-marker-wrap" style="color:${bg};">
                        <div class="pending-marker-ring"></div>
                        <div class="pending-marker-dot" style="background:${bg};"></div>
                    </div>
                `,
                iconSize: [16, 16],
                iconAnchor: [8, 8],
                popupAnchor: [0, -8],
            });
        }

        const emoji = categoryIcons[category] || '📍';
        return L.divIcon({
            className: '',
            html: `<div style="
                width: 34px; height: 34px; border-radius: 50%;
                background: ${bg}; border: 2px solid #fff;
                display: flex; align-items: center; justify-content: center;
                font-size: 16px; box-shadow: 0 2px 6px rgba(0,0,0,.35);
            ">${emoji}</div>`,
            iconSize: [34, 34],
            iconAnchor: [17, 17],
            popupAnchor: [0, -17],
        });
    }

    function isToday(isoString) {
        if (!isoString) return false;
        const d = new Date(isoString);
        const now = new Date();
        return d.getFullYear() === now.getFullYear()
            && d.getMonth() === now.getMonth()
            && d.getDate() === now.getDate();
    }

    // Formats an ISO date string as "Sep 21, 2026 · 3:45 PM"
    function formatReportDate(isoString) {
        if (!isoString) return 'Unknown date';
        const d = new Date(isoString);
        const datePart = d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        const timePart = d.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
        return `${datePart} · ${timePart}`;
    }

    let incidents = [];
    let markers = [];
    let seenIds = new Set();
    let firstLoad = true;

    function renderMarkers() {
        markers.forEach(m => liveMap.removeLayer(m));
        markers = [];

        const showReports = document.getElementById('showReportsToggle').checked;
        if (!showReports) return;

        const filterStatus = document.getElementById('statusFilter').value;
        const filterCategory = document.getElementById('categoryFilter').value;
        const todayOnly = document.getElementById('todayToggle').checked;

        incidents
            .filter(i => (!filterStatus || i.status === filterStatus.toLowerCase()))
            .filter(i => (!filterCategory || i.category === filterCategory))
            .filter(i => (!todayOnly || isToday(i.reported_at)))
            .forEach(i => {
                if (!i.lat || !i.lng) return;
                const marker = L.marker([i.lat, i.lng], {
                    icon: makeIcon(i.category, i.status)
                }).addTo(liveMap);
                marker.bindPopup(`
                    <strong>${i.label}</strong><br>
                    <span style="text-transform:capitalize;color:${statusColors[i.status]}">${i.status}</span>
                    <div class="popup-report-date">🕒 ${formatReportDate(i.reported_at)}</div>
                `);
                markers.push(marker);
            });
    }

    function showToast(incident) {
        const el = document.createElement('div');
        el.className = 'toast align-items-center text-white border-0 show';
        el.style.background = categoryColors[incident.category] || '#B3251A';
        el.style.minWidth = '280px';
        el.innerHTML = `
            <div class="d-flex">
                <div class="toast-body" style="font-size:.85rem;">
                    <strong>New ${incident.category} Report</strong><br>${incident.label}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>`;
        document.getElementById('toastStack').appendChild(el);
        setTimeout(() => el.remove(), 8000);
    }

    async function fetchIncidents() {
        try {
            const res = await fetch("{{ route('gis.live') }}", { headers: { 'Accept': 'application/json' } });
            const data = await res.json();

            if (!firstLoad) {
                data.forEach(i => {
                    if (!seenIds.has(i.id) && isToday(i.reported_at)) {
                        showToast(i);
                    }
                });
            }

            seenIds = new Set(data.map(i => i.id));
            incidents = data;
            firstLoad = false;

            renderMarkers();
            document.getElementById('lastUpdated').textContent = 'Updated ' + new Date().toLocaleTimeString();
        } catch (e) {
            document.getElementById('liveIndicator').innerHTML = '<span class="status-dot" style="background:currentColor;"></span> Connection lost';
        }
    }
      
    // Focus the map when opened from the new-report board (?lat=..&lng=..)
const focusParams = new URLSearchParams(location.search);
if (focusParams.has('lat') && focusParams.has('lng')) {
    const fLat = parseFloat(focusParams.get('lat')), fLng = parseFloat(focusParams.get('lng'));
    if (!isNaN(fLat) && !isNaN(fLng)) liveMap.setView([fLat, fLng], 17);
}
    fetchIncidents();
    setInterval(fetchIncidents, 10000); // poll every 10 seconds

    document.getElementById('statusFilter').addEventListener('change', renderMarkers);
    document.getElementById('categoryFilter').addEventListener('change', renderMarkers);
    document.getElementById('todayToggle').addEventListener('change', renderMarkers);
    document.getElementById('showReportsToggle').addEventListener('change', renderMarkers);

</script>
@endpush