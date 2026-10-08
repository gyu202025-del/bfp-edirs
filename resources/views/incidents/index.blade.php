@extends('layouts.app')

@section('title', 'Incidents')

@push('styles')
<style>#incidentModalMap { height: 220px; border-radius: var(--radius, 10px); }</style>
@endpush

@section('content')

@php
    $statusClass = [
        'Pending' => 'status-pending', 'Verified' => 'status-verified', 'Dispatched' => 'status-dispatched',
        'Responding' => 'status-responding', 'Resolved' => 'status-resolved', 'Cancelled' => 'status-cancelled',
    ];
@endphp

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h6 class="fw-semibold mb-0">Incident Reports</h6>
        <div class="text-secondary" style="font-size:.82rem;">All incidents reported via the public report form</div>
    </div>
</div>

@if (session('success'))
    <div class="alert alert-success py-2" style="font-size:.85rem;">{{ session('success') }}</div>
@endif

{{-- Filters --}}
<div class="card-flat p-3 mb-3 d-flex flex-wrap gap-2 align-items-center">
    <input type="text" class="form-control form-control-sm" style="max-width:220px;" placeholder="Search incident no. or location">
    <select class="form-select form-select-sm" style="width:auto;">
        <option>All statuses</option>
        <option>Pending</option><option>Verified</option><option>Dispatched</option>
        <option>Responding</option><option>Resolved</option><option>Cancelled</option>
    </select>
    <select class="form-select form-select-sm" style="width:auto;">
        <option>All categories</option>
        <option>Fire</option><option>Medical</option><option>Crime/Security</option><option>Accident</option>
    </select>
</div>

<div class="card-flat p-0">
    <table class="table table-clean mb-0">
        <thead>
            <tr>
                <th>Incident No.</th><th>Category</th><th>Location</th><th>Reporter</th>
                <th>Source</th><th>Status</th><th>Reported</th><th></th>
            </tr>
        </thead>
        <tbody>
        @forelse ($incidents as $i)
            <tr>
                <td class="mono">{{ $i->incident_number }}</td>
                <td class="fw-semibold">{{ $i->category ?? $i->incident_type }}</td>
                <td><i class="bi bi-geo-alt text-secondary"></i> {{ $i->landmark ?? number_format($i->latitude, 4) . ', ' . number_format($i->longitude, 4) }}</td>
                <td>{{ $i->reporter_name ?? 'Anonymous' }}</td>
                <td><span class="badge text-bg-light border">{{ $i->report_source }}</span></td>
                <td>
                    <span class="status-pill {{ $statusClass[$i->status] ?? 'status-pending' }}">
                        <span class="status-dot live" style="background:currentColor;"></span> {{ $i->status }}
                    </span>
                </td>
                <td class="text-secondary" style="font-size:.8rem;">{{ $i->reported_at?->format('M d, Y · g:i A') }}</td>
                <td>
                    <button class="btn btn-sm btn-outline-secondary view-incident-btn"
                        data-bs-toggle="modal" data-bs-target="#incidentModal"
                        data-id="{{ $i->id }}"
                        data-no="{{ $i->incident_number }}"
                        data-category="{{ $i->category ?? $i->incident_type }}"
                        data-desc="{{ $i->description }}"
                        data-landmark="{{ $i->landmark }}"
                        data-reporter="{{ $i->reporter_name ?? 'Anonymous' }}"
                        data-contact="{{ $i->reporter_contact ?? '—' }}"
                        data-status="{{ $i->status }}"
                        data-lat="{{ $i->latitude }}"
                        data-lng="{{ $i->longitude }}">
                        View
                    </button>
                </td>
            </tr>
        @empty
            <tr><td colspan="8" class="text-center text-secondary py-4">No incidents reported yet.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

{{-- View / Verify modal --}}
<div class="modal fade" id="incidentModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h6 class="fw-semibold mb-0" id="modalIncidentNo">—</h6>
                    <div class="text-secondary" style="font-size:.8rem;" id="modalCategory">—</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="px-3 pt-3">
                <form method="POST" id="requestAssistanceForm">
                    @csrf
                    <button type="submit" class="btn btn-outline-warning btn-sm w-100">🤝 Backup Request</button>
                </form>
            </div>

            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="mb-2"><span class="text-secondary" style="font-size:.78rem;">Reporter</span><div class="fw-semibold" id="modalReporter">—</div></div>
                        <div class="mb-2"><span class="text-secondary" style="font-size:.78rem;">Contact</span><div id="modalContact">—</div></div>
                        <div class="mb-2"><span class="text-secondary" style="font-size:.78rem;">Landmark</span><div id="modalLandmark">—</div></div>
                        <div class="mb-3"><span class="text-secondary" style="font-size:.78rem;">Description</span><div id="modalDesc">—</div></div>

                        <span class="text-secondary" style="font-size:.78rem;">Nearby Stations</span>
                        <div id="nearbyStationsList" style="font-size:.82rem; max-height:150px; overflow-y:auto;">
                            <div class="text-secondary" style="font-size:.78rem;">Loading...</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div id="incidentModalMap" class="gis-map"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <span class="status-pill status-pending me-auto" id="modalStatusPill">Pending</span>

                <form method="POST" id="cancelForm" style="display:inline;">
                    @csrf
                    <button type="submit" class="btn btn-outline-danger btn-sm">Cancel Report</button>
                </form>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
    let incidentModalMap;
    document.querySelectorAll('.view-incident-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            document.getElementById('modalIncidentNo').textContent = this.dataset.no;
            document.getElementById('modalCategory').textContent = this.dataset.category;
            document.getElementById('modalReporter').textContent = this.dataset.reporter;
            document.getElementById('modalContact').textContent = this.dataset.contact;
            document.getElementById('modalLandmark').textContent = this.dataset.landmark || '—';
            document.getElementById('modalDesc').textContent = this.dataset.desc;
            document.getElementById('modalStatusPill').textContent = this.dataset.status;

            const lat = parseFloat(this.dataset.lat), lng = parseFloat(this.dataset.lng);
            document.getElementById('incidentModal').dataset.lat = lat;
            document.getElementById('incidentModal').dataset.lng = lng;
            document.getElementById('incidentModal').dataset.id = this.dataset.id;

            document.getElementById('requestAssistanceForm').action = `/incidents/${this.dataset.id}/request-assistance`;
            document.getElementById('cancelForm').action = `/incidents/${this.dataset.id}/cancel`;
        });
    });

    document.getElementById('incidentModal').addEventListener('shown.bs.modal', function () {
        const lat = parseFloat(this.dataset.lat), lng = parseFloat(this.dataset.lng);
        if (incidentModalMap) { incidentModalMap.remove(); }
        incidentModalMap = L.map('incidentModalMap').setView([lat, lng], 15);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(incidentModalMap);
        L.circleMarker([lat, lng], { radius: 9, color: '#B3251A', fillColor: '#B3251A', fillOpacity: .85 }).addTo(incidentModalMap);

        const incidentId = this.dataset.id;
        const listEl = document.getElementById('nearbyStationsList');
        listEl.innerHTML = '<div class="text-secondary" style="font-size:.78rem;">Loading...</div>';

        fetch(`/incidents/${incidentId}/nearby-stations`, { headers: { 'Accept': 'application/json' } })
            .then(res => res.json())
            .then(stations => {
                if (stations.length === 0) {
                    listEl.innerHTML = '<div class="text-secondary" style="font-size:.78rem;">No active stations found.</div>';
                    return;
                }
                listEl.innerHTML = stations.map(s => `
                    <div class="d-flex justify-content-between align-items-center py-1" style="border-bottom:1px solid var(--border,#E4E7EC);">
                        <div>
                            <span class="fw-semibold">${s.station_name}</span>
                            ${s.is_home ? '<span class="badge text-bg-light border ms-1" style="font-size:.65rem;">Home</span>' : ''}
                            <div class="text-secondary" style="font-size:.72rem;">${s.municipality}</div>
                        </div>
                        <div class="text-end text-secondary" style="font-size:.75rem;">
                            ${s.km} km<br>${s.mi} mi
                        </div>
                    </div>
                `).join('');
            })
            .catch(() => {
                listEl.innerHTML = '<div class="text-danger" style="font-size:.78rem;">Could not load station distances.</div>';
            });
    });
</script>
@endpush