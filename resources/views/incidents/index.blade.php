@extends('layouts.app')
 
@section('title', 'Incidents')
 
@push('styles')
<style>#incidentModalMap { height: 220px; border-radius: var(--radius, 10px); }</style>
@endpush
 
@section('content')
 
@php
    $statusClass = [
        'Pending' => 'status-pending', 'Dispatched' => 'status-dispatched',
        'Resolved' => 'status-resolved', 'Cancelled' => 'status-cancelled',
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
@if (session('error'))
    <div class="alert alert-danger py-2" style="font-size:.85rem;">{{ session('error') }}</div>
@endif
 
{{-- Filters --}}
<div class="card-flat p-3 mb-3 d-flex flex-wrap gap-2 align-items-center">
    <input type="text" class="form-control form-control-sm" style="max-width:220px;" placeholder="Search incident no. or location">
    <select class="form-select form-select-sm" style="width:auto;">
        <option>All statuses</option>
        <option>Pending</option><option>Dispatched</option>
        <option>Resolved</option><option>Cancelled</option>
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
                <th>Incident No.</th><th>Category</th><th>Location</th>
                <th>Incident Status</th><th>Reported</th><th>Department Assignment</th><th></th>
            </tr>
        </thead>
        <tbody>
        @forelse ($incidents as $i)
            <tr>
                <td class="mono">{{ $i->display_number }}</td>
                <td class="fw-semibold">{{ $i->category ?? $i->incident_type }}</td>
                <td><i class="bi bi-geo-alt text-secondary"></i> {{ $i->location_text }}</td>
                <td>
                    <span class="status-pill {{ $statusClass[$i->status] ?? 'status-pending' }}">
                        <span class="status-dot live" style="background:currentColor;"></span> {{ $i->status }}
                    </span>
                </td>
                <td class="text-secondary" style="font-size:.8rem;">{{ $i->reported_at?->format('M d, Y · g:i A') }}</td>
                <td style="font-size:.78rem;">
                    @if(auth()->user()->isAdministrator())
                        @forelse($i->assignments as $assignment)
                            <span class="badge text-bg-light border">{{ $assignment->department->name }}: {{ $assignment->status }}</span>
                        @empty
                            <span class="text-secondary">No department assignment</span>
                        @endforelse
                    @else
                        {{ $myAssignment[$i->id]?->status ?? '—' }}
                    @endif
                </td>
                <td>
                    <button class="btn btn-sm btn-outline-secondary view-incident-btn"
                        data-bs-toggle="modal" data-bs-target="#incidentModal"
                        data-id="{{ $i->id }}"
                        data-no="{{ $i->display_number }}"
                        data-category="{{ $i->category ?? $i->incident_type }}"
                        data-desc="{{ $i->description }}"
                        data-location="{{ $i->location_text }}"
                        data-landmark="{{ $i->landmark }}"
                        data-status="{{ $i->status }}"
                        data-assignment-status="{{ $myAssignment[$i->id]?->status ?? '' }}"
                        data-lat="{{ $i->latitude }}"
                        data-lng="{{ $i->longitude }}">
                        View
                    </button>
                </td>
            </tr>
        @empty
            <tr><td colspan="7" class="text-center text-secondary py-4">No incidents reported yet.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
 
{{-- View modal --}}
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
                @unless(auth()->user()->isAdministrator())
                    <form method="POST" id="acknowledgeForm" class="mb-2">
                        @csrf
                        <button type="submit" class="btn btn-outline-primary btn-sm w-100">Acknowledge assignment</button>
                    </form>
                @endunless
                @if(auth()->user()->isAdministrator() || auth()->user()->hasDepartment('BFP'))
                    <form method="POST" id="requestAssistanceForm">
                        @csrf
                        <button type="submit" class="btn btn-outline-warning btn-sm w-100">🤝 Backup Request</button>
                    </form>
                @endif
            </div>
 
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="mb-2"><span class="text-secondary" style="font-size:.78rem;">Location</span><div class="fw-semibold" id="modalLocation">—</div></div>
                        <div class="mb-2"><span class="text-secondary" style="font-size:.78rem;">Landmark</span><div id="modalLandmark">—</div></div>
                        <div class="mb-3"><span class="text-secondary" style="font-size:.78rem;">Description</span><div id="modalDesc">—</div></div>
 
                        @if(auth()->user()->isAdministrator() || auth()->user()->hasDepartment('BFP'))
                            <span class="text-secondary" style="font-size:.78rem;">Nearby Stations</span>
                            <div id="nearbyStationsList" style="font-size:.82rem; max-height:150px; overflow-y:auto;">
                                <div class="text-secondary" style="font-size:.78rem;">Loading...</div>
                            </div>
                        @endif
                    </div>
                    <div class="col-md-6">
                        <div id="incidentModalMap" class="gis-map"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <span class="status-pill status-pending me-auto" id="modalStatusPill">Pending</span>
 
                {{-- Only shown for Dispatched incidents --}}
                <form method="POST" id="resolveForm" style="display:none;"
                      onsubmit="return confirm('Mark this incident as resolved?')">
                    @csrf
                    <button type="submit" class="btn btn-success btn-sm">✔ Mark as Resolved</button>
                </form>
 
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
    const isAdmin = @json(auth()->user()->isAdministrator());
    let incidentModalMap;
    document.querySelectorAll('.view-incident-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            document.getElementById('modalIncidentNo').textContent = 'Incident #' + this.dataset.no;
            document.getElementById('modalCategory').textContent = this.dataset.category;
            document.getElementById('modalLocation').textContent = this.dataset.location || '—';
            document.getElementById('modalLandmark').textContent = this.dataset.landmark || '—';
            document.getElementById('modalDesc').textContent = this.dataset.desc;
            document.getElementById('modalStatusPill').textContent = this.dataset.status;
 
            const lat = parseFloat(this.dataset.lat), lng = parseFloat(this.dataset.lng);
            document.getElementById('incidentModal').dataset.lat = lat;
            document.getElementById('incidentModal').dataset.lng = lng;
            document.getElementById('incidentModal').dataset.id = this.dataset.id;
 
            const assistanceForm = document.getElementById('requestAssistanceForm');
            if (assistanceForm) assistanceForm.action = `/incidents/${this.dataset.id}/request-assistance`;
            const acknowledgeForm = document.getElementById('acknowledgeForm');
            if (acknowledgeForm) {
                acknowledgeForm.action = `/incidents/${this.dataset.id}/acknowledge`;
                acknowledgeForm.style.display = this.dataset.assignmentStatus === 'Pending' ? 'inline' : 'none';
            }
            document.getElementById('cancelForm').action = `/incidents/${this.dataset.id}/cancel`;
 
            // Resolve button: only for Dispatched incidents
            document.getElementById('resolveForm').action = `/incidents/${this.dataset.id}/resolve`;
            document.getElementById('resolveForm').style.display = isAdmin
                ? ((this.dataset.status || '').toLowerCase() === 'dispatched' ? 'inline' : 'none')
                : (this.dataset.assignmentStatus === 'Acknowledged' ? 'inline' : 'none');
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
        if (!listEl) return;
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