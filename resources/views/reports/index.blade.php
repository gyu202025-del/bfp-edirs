@extends('layouts.app')
@section('title', 'Reports')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="fw-semibold mb-0">Reports</h5>
    <div class="btn-group">
        <button class="btn btn-sm btn-outline-secondary">Daily</button>
        <button class="btn btn-sm btn-outline-secondary">Weekly</button>
        <button class="btn btn-sm btn-primary-fire">Monthly</button>
        <button class="btn btn-sm btn-outline-secondary">Yearly</button>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-4"><div class="card-flat p-3"><h6 class="fw-semibold small">Fire Causes</h6><canvas id="pieCauses" height="200"></canvas></div></div>
    <div class="col-lg-4"><div class="card-flat p-3"><h6 class="fw-semibold small">Monthly Incidents</h6><canvas id="barIncidents" height="200"></canvas></div></div>
    <div class="col-lg-4"><div class="card-flat p-3"><h6 class="fw-semibold small">Response Time (min)</h6><canvas id="lineResponse" height="200"></canvas></div></div>
</div>

<div class="card-flat p-3 d-flex justify-content-end gap-2">
    <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-file-earmark-pdf"></i> PDF</button>
    <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-file-earmark-excel"></i> Excel</button>
    <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer"></i> Print</button>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('pieCauses'), {
    type: 'pie',
    data: { labels: ['Electrical','Cooking','Vehicular','Grass/Rubbish','Others'],
        datasets: [{ data: [38,27,14,16,5], backgroundColor: ['#C62828','#FFC107','#1565C0','#2E7D32','#8A6D00'] }] }
});
new Chart(document.getElementById('barIncidents'), {
    type: 'bar',
    data: { labels: ['Feb','Mar','Apr','May','Jun','Jul'],
        datasets: [{ label: 'Incidents', data: [18,22,15,27,24,30], backgroundColor: '#C62828' }] },
    options: { plugins: { legend: { display: false } } }
});
new Chart(document.getElementById('lineResponse'), {
    type: 'line',
    data: { labels: ['Feb','Mar','Apr','May','Jun','Jul'],
        datasets: [{ label: 'Avg. response (min)', data: [14,13,12,11,10.5,9.8], borderColor: '#1B263B', tension: .35 }] }
});
</script>
@endpush