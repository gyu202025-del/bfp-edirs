@extends('layouts.app')
@section('title', 'Fire Stations')

@section('content')
@php
    $stations = [
        ['name'=>'Tacloban Central Fire Station','commander'=>'SFO1 M. Bautista','contact'=>'(053) 321-4455','personnel'=>18,'coverage'=>'Tacloban City','status'=>'Active'],
        ['name'=>'Palo Fire Station','commander'=>'FO1 R. Aguilar','contact'=>'(053) 323-1190','personnel'=>10,'coverage'=>'Palo, Sta. Fe','status'=>'Active'],
        ['name'=>'Ormoc Fire Station','commander'=>'FO2 D. Salazar','contact'=>'(053) 255-7810','personnel'=>14,'coverage'=>'Ormoc City','status'=>'Active'],
    ];
@endphp

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="fw-semibold mb-0">Fire Stations</h5>
    <button class="btn btn-primary-fire btn-sm"><i class="bi bi-plus-lg"></i> Add Station</button>
</div>

<div class="row g-3">
@foreach ($stations as $s)
    <div class="col-md-6 col-xl-4">
        <div class="station-card">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <h6 class="fw-semibold mb-0">{{ $s['name'] }}</h6>
                <span><span class="station-status-dot" style="background:#2E7D32;"></span>{{ $s['status'] }}</span>
            </div>
            <div class="text-muted small mb-1"><i class="bi bi-person-badge me-1"></i>{{ $s['commander'] }}</div>
            <div class="text-muted small mb-1"><i class="bi bi-telephone me-1"></i>{{ $s['contact'] }}</div>
            <div class="text-muted small mb-1"><i class="bi bi-people me-1"></i>{{ $s['personnel'] }} personnel</div>
            <div class="text-muted small mb-3"><i class="bi bi-geo me-1"></i>{{ $s['coverage'] }}</div>
            <div class="d-flex gap-2">
                <button class="btn btn-sm btn-outline-secondary flex-fill">View</button>
                <button class="btn btn-sm btn-outline-secondary flex-fill">Edit</button>
                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
            </div>
            <button class="btn btn-sm btn-outline-secondary w-100 mt-2"><i class="bi bi-geo-alt"></i> Locate on Map</button>
        </div>
    </div>
@endforeach
</div>
@endsection