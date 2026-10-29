@extends('layouts.app')

@section('title', 'Reports')

@push('styles')
<style>
    .stat-card .num { font-size: 1.9rem; font-weight: 700; line-height: 1; }
    .chart-box { position: relative; height: 260px; }
    @media print {
        .no-print, .sidebar, #sidebar, nav.navbar, footer, #alertBanner { display: none !important; }
        .main-content, main, .content-wrapper, .app-main { margin: 0 !important; padding: 0 !important; width: 100% !important; }
        .card { break-inside: avoid; box-shadow: none !important; }
        body { background: #fff !important; }
    }
</style>
@endpush

@section('content')
@php
    $catLabels = $byCategory->keys()->values();
    $catData   = $byCategory->values();
    $munLabels = $byMunicipality->keys()->values();
    $munData   = $byMunicipality->values();
    $trendLabels = $trend->keys()->values();
    $trendData   = $trend->values();
    $stnLabels = $perStation->keys()->values();
    $stnData   = $perStation->values();
    $statLabels = $byStatus->keys()->values();
    $statData   = $byStatus->values();
@endphp

{{-- Print header --}}
<div class="d-none d-print-block mb-3 text-center">
    <h4 class="mb-0">Emergency Disaster Incident Reporting System (BFP-EDIRS) — Leyte</h4>
    <div>Incident Report Summary</div>
    <small>
        {{ $from->format('M d, Y') }} – {{ $to->format('M d, Y') }}
        @if($municipality) | {{ $municipality }} @endif
        @if($category) | {{ $category }} @endif
        @if($status) | {{ $status }} @endif
    </small>
</div>

<div class="d-flex justify-content-between align-items-center mb-3 no-print">
    <h4 class="mb-0">Reports</h4>
    <button class="btn btn-danger" onclick="window.print()">🖨️ Print / Save as PDF</button>
</div>

{{-- Filters --}}
<form method="GET" action="{{ route('reports.index') }}" class="card card-body mb-3 no-print">
    <div class="row g-2 align-items-end">
        <div class="col-6 col-md-2">
            <label class="form-label small mb-1">From</label>
            <input type="date" name="from" value="{{ $from->format('Y-m-d') }}" class="form-control form-control-sm">
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small mb-1">To</label>
            <input type="date" name="to" value="{{ $to->format('Y-m-d') }}" class="form-control form-control-sm">
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small mb-1">Category</label>
            <select name="category" class="form-select form-select-sm">
                <option value="">All</option>
                @foreach($categories as $c)
                    <option value="{{ $c }}" @selected($category === $c)>{{ $c }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small mb-1">Status</label>
            <select name="status" class="form-select form-select-sm">
                <option value="">All</option>
                @foreach($statuses as $s)
                    <option value="{{ $s }}" @selected($status === $s)>{{ $s }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-12 col-md-2">
            <label class="form-label small mb-1">Municipality</label>
            @if($isAdmin)
                <select name="municipality" class="form-select form-select-sm">
                    <option value="">All</option>
                    @foreach($municipalities as $m)
                        <option value="{{ $m }}" @selected($municipality === $m)>{{ $m }}</option>
                    @endforeach
                </select>
            @else
                <input type="text" class="form-control form-control-sm" value="{{ $municipality }}" disabled>
            @endif
        </div>
        <div class="col-12 col-md-2 d-flex gap-1">
            <button class="btn btn-sm btn-primary flex-fill">Apply</button>
            <a href="{{ route('reports.index') }}" class="btn btn-sm btn-outline-secondary flex-fill">Reset</a>
        </div>
    </div>
</form>

{{-- Summary cards --}}
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3 col-xl">
        <div class="card stat-card h-100"><div class="card-body">
            <div class="text-muted small">Total Incidents</div><div class="num">{{ $summary['total'] }}</div>
        </div></div>
    </div>
    <div class="col-6 col-md-3 col-xl">
        <div class="card stat-card h-100"><div class="card-body">
            <div class="text-muted small">Pending</div><div class="num text-warning">{{ $summary['pending'] }}</div>
        </div></div>
    </div>
    <div class="col-6 col-md-3 col-xl">
        <div class="card stat-card h-100"><div class="card-body">
            <div class="text-muted small">Dispatched</div><div class="num text-danger">{{ $summary['active'] }}</div>
        </div></div>
    </div>
    <div class="col-6 col-md-3 col-xl">
        <div class="card stat-card h-100"><div class="card-body">
            <div class="text-muted small">Resolved</div><div class="num text-success">{{ $summary['resolved'] }}</div>
        </div></div>
    </div>
    <div class="col-12 col-md-3 col-xl">
        <div class="card stat-card h-100"><div class="card-body">
            <div class="text-muted small">Cancelled</div><div class="num text-secondary">{{ $summary['cancelled'] }}</div>
        </div></div>
    </div>
</div>

{{-- Charts --}}
<div class="row g-3 mb-3">
    <div class="col-12 col-lg-8">
        <div class="card h-100"><div class="card-body">
            <h6 class="mb-2">Incidents Over Time</h6>
            <div class="chart-box"><canvas id="trendChart"></canvas></div>
        </div></div>
    </div>
    <div class="col-12 col-lg-4">
        <div class="card h-100"><div class="card-body">
            <h6 class="mb-2">By Category</h6>
            <div class="chart-box"><canvas id="categoryChart"></canvas></div>
        </div></div>
    </div>
    <div class="col-12 col-lg-6">
        <div class="card h-100"><div class="card-body">
            <h6 class="mb-2">By Municipality (Top 10)</h6>
            <div class="chart-box"><canvas id="municipalityChart"></canvas></div>
        </div></div>
    </div>
    <div class="col-12 col-lg-6">
        <div class="card h-100"><div class="card-body">
            <h6 class="mb-2">By Status</h6>
            <div class="chart-box"><canvas id="statusChart"></canvas></div>
        </div></div>
    </div>
</div>

{{-- Response / dispatch stats --}}
<div class="row g-3 mb-3">
    <div class="col-12 col-lg-5">
        <div class="card h-100"><div class="card-body">
            <h6 class="mb-3">Response &amp; Dispatch</h6>
            <table class="table table-sm mb-0">
                <tr><td>Total dispatches</td><td class="text-end fw-bold">{{ $dispatchStats['total'] }}</td></tr>
                <tr><td>Completed dispatches</td><td class="text-end fw-bold">{{ $dispatchStats['completed'] }}</td></tr>
                <tr><td>Average time to complete</td>
                    <td class="text-end fw-bold">
                        {{ $dispatchStats['avg_minutes'] !== null ? $dispatchStats['avg_minutes'].' min' : '—' }}
                    </td></tr>
                <tr><td>Mutual aid / backup requests</td><td class="text-end fw-bold">{{ $dispatchStats['mutual_aid'] }}</td></tr>
            </table>
        </div></div>
    </div>
    <div class="col-12 col-lg-7">
        <div class="card h-100"><div class="card-body">
            <h6 class="mb-2">Dispatches per Station</h6>
            <div class="chart-box"><canvas id="stationChart"></canvas></div>
        </div></div>
    </div>
</div>

{{-- Incident list --}}
<div class="card mb-4">
    <div class="card-header bg-white"><strong>Incident List</strong> <span class="text-muted small">({{ $incidents->count() }})</span></div>
    <div class="table-responsive">
        <table class="table table-sm table-striped align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Incident No.</th><th>Date &amp; Time</th><th>Category</th>
                    <th>Municipality</th><th>Barangay</th><th>Responding Station</th><th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($incidents as $i)
                    <tr>
                        <td>{{ $i->incident_number }}</td>
                        <td>{{ $i->created_at->format('M d, Y h:i A') }}</td>
                        <td>{{ $i->category }}</td>
                        <td>{{ $i->municipality }}</td>
                        <td>{{ $i->barangay }}</td>
                        <td>{{ optional(optional($i->dispatch)->station)->station_name ?? '—' }}</td>
                        <td>{{ $i->status }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">No incidents found for the selected filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
    const palette = ['#c62828', '#1565c0', '#ef6c00', '#2e7d32', '#6a1b9a', '#00838f', '#5d4037', '#546e7a'];
    const base = { responsive: true, maintainAspectRatio: false, animation: false };
    const noLegend = { plugins: { legend: { display: false } } };
    const intAxis = { y: { beginAtZero: true, ticks: { precision: 0 } } };

    new Chart(document.getElementById('trendChart'), {
        type: 'line',
        data: { labels: @json($trendLabels), datasets: [{ label: 'Incidents', data: @json($trendData),
            borderColor: '#c62828', backgroundColor: 'rgba(198,40,40,.12)', fill: true, tension: .25 }] },
        options: { ...base, ...noLegend, scales: intAxis }
    });

    new Chart(document.getElementById('categoryChart'), {
        type: 'doughnut',
        data: { labels: @json($catLabels), datasets: [{ data: @json($catData), backgroundColor: palette }] },
        options: { ...base, plugins: { legend: { position: 'bottom' } } }
    });

    new Chart(document.getElementById('municipalityChart'), {
        type: 'bar',
        data: { labels: @json($munLabels), datasets: [{ data: @json($munData), backgroundColor: '#1565c0' }] },
        options: { ...base, ...noLegend, scales: intAxis }
    });

    new Chart(document.getElementById('statusChart'), {
        type: 'bar',
        data: { labels: @json($statLabels), datasets: [{ data: @json($statData), backgroundColor: palette }] },
        options: { ...base, ...noLegend, scales: intAxis }
    });

    new Chart(document.getElementById('stationChart'), {
        type: 'bar',
        data: { labels: @json($stnLabels), datasets: [{ data: @json($stnData), backgroundColor: '#ef6c00' }] },
        options: { ...base, ...noLegend, indexAxis: 'y', scales: { x: { beginAtZero: true, ticks: { precision: 0 } } } }
    });
</script>
@endpush