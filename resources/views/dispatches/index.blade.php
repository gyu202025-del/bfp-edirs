@extends('layouts.app')

@section('title', 'Dispatch')

@section('content')

@php
    $statusClass = ['Pending' => 'dispatch-pending', 'Responding' => 'dispatch-responding', 'Completed' => 'dispatch-completed', 'Cancelled' => 'dispatch-cancelled'];
@endphp

<div class="mb-3">
    <h6 class="fw-semibold mb-0">Dispatch Console</h6>
    <div class="text-secondary" style="font-size:.82rem;">Assign fire stations and units to verified incidents</div>
</div>

@if (session('success'))
    <div class="alert alert-success py-2" style="font-size:.85rem;">{{ session('success') }}</div>
@endif

   

<div class="row g-3">
    {{-- Awaiting dispatch --}}
    <div class="col-lg-4">
        <div class="card-flat p-3 h-100">
            <h6 class="fw-semibold" style="font-size:.9rem;">Awaiting Dispatch</h6>
            @forelse ($queue as $q)
            <div class="border rounded-3 p-2 mb-2" style="border-color: var(--border, #E4E7EC) !important;">
                <div class="d-flex justify-content-between">
                    <span class="mono" style="font-size:.75rem; color:var(--text-muted,#6B7280);">{{ $q->incident_number }}</span>
                    <span class="status-pill status-verified" style="font-size:.68rem;">Verified</span>
                </div>
                <div class="fw-semibold" style="font-size:.85rem;">{{ $q->category ?? $q->incident_type }}</div>
                <div class="text-secondary" style="font-size:.78rem;"><i class="bi bi-geo-alt"></i> {{ $q->landmark ?? 'No landmark given' }}</div>
                <button class="btn btn-bfp btn-sm w-100 mt-2 dispatch-btn"
                    data-bs-toggle="modal" data-bs-target="#dispatchModal"
                    data-incident-id="{{ $q->id }}" data-incident-no="{{ $q->incident_number }}">
                    <i class="bi bi-truck"></i> Dispatch Unit
                </button>
            </div>
            @empty
                <p class="text-secondary text-center py-3" style="font-size:.85rem;">No verified incidents waiting.</p>
            @endforelse
        </div>
    </div>

    {{-- Active dispatches --}}
    <div class="col-lg-8">
        <div class="card-flat p-0">
            <div class="p-3 pb-0"><h6 class="fw-semibold" style="font-size:.9rem;">Active Dispatches</h6></div>
            <table class="table table-clean mb-0">
                <thead><tr><th>Incident</th><th>Station</th><th>Dispatcher</th><th>Dispatch Time</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @forelse ($active as $a)
                    <tr>
                        <td><span class="mono d-block" style="font-size:.75rem;">{{ $a->incident->incident_number ?? '—' }}</span><span class="fw-semibold" style="font-size:.85rem;">{{ $a->incident->category ?? $a->incident->incident_type ?? '—' }}</span></td>
                        <td>{{ $a->station->station_name ?? '—' }}</td>
                        <td>
                           @if ($a->dispatcher)
                              {{ $a->dispatcher->name }}
                            @else
        <form method="POST" action="{{ route('dispatches.assignOfficer', $a->id) }}" class="d-flex gap-1">
            @csrf
            <select name="dispatcher_id" class="form-select form-select-sm" required style="font-size:.75rem;">
                <option value="" selected disabled>Assign officer...</option>
                @foreach ($personnel as $p)
                    <option value="{{ $p->id }}">{{ $p->name }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn btn-sm btn-bfp">Go</button>
        </form>
    @endif
</td>
                        <td class="text-secondary" style="font-size:.8rem;">{{ $a->dispatch_time?->format('M d, g:i A') }}</td>
                        <td><span class="status-pill {{ $statusClass[$a->status] }}"><span class="status-dot live" style="background:currentColor;"></span>{{ $a->status }}</span></td>
                        <td>
                            <form method="POST" action="{{ route('dispatches.complete', $a->id) }}">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-secondary">Mark Completed</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-secondary py-3">No active dispatches.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        {{-- Completed --}}
        <div class="card-flat p-0 mt-3">
            <div class="p-3 pb-0"><h6 class="fw-semibold" style="font-size:.9rem;">Recently Completed</h6></div>
            <table class="table table-clean mb-0">
                <thead><tr><th>Incident</th><th>Station</th><th>Dispatch</th><th>Completion</th></tr></thead>
                <tbody>
                @forelse ($completed as $c)
                    <tr>
                        <td><span class="mono d-block" style="font-size:.75rem;">{{ $c->incident->incident_number ?? '—' }}</span></td>
                        <td>{{ $c->station->station_name ?? '—' }}</td>
                        <td class="text-secondary" style="font-size:.8rem;">{{ $c->dispatch_time?->format('M d, g:i A') }}</td>
                        <td class="text-secondary" style="font-size:.8rem;">{{ $c->completion_time?->format('M d, g:i A') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-secondary py-3">No completed dispatches yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Dispatch assignment modal --}}
<div class="modal fade" id="dispatchModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('dispatches.store') }}">
                @csrf
                <input type="hidden" name="incident_id" id="modalIncidentId">
                <div class="modal-header">
                    <h6 class="fw-semibold mb-0">Dispatch Unit — <span id="modalIncidentNo"></span></h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" style="font-size:.85rem;">Fire Station</label>
                        <select class="form-select" name="station_id" required>
                            @foreach ($stations as $s)
                                <option value="{{ $s->id }}">{{ $s->station_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" style="font-size:.85rem;">Dispatcher / Responding Officer</label>
                        <select class="form-select" name="dispatcher_id" required>
                            @foreach ($personnel as $p)
                                <option value="{{ $p->id }}">{{ $p->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label" style="font-size:.85rem;">Remarks</label>
                        <textarea class="form-control" name="remarks" rows="2" placeholder="Optional dispatch notes"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-bfp btn-sm"><i class="bi bi-truck"></i> Confirm Dispatch</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
    document.querySelectorAll('.dispatch-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            document.getElementById('modalIncidentId').value = this.dataset.incidentId;
            document.getElementById('modalIncidentNo').textContent = this.dataset.incidentNo;
        });
    });
</script>
@endpush