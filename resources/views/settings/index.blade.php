@extends('layouts.app')
@section('title', 'Settings')

@section('content')
<h5 class="fw-semibold mb-3">Settings</h5>
<div class="row g-3">
    <div class="col-md-3">
        <div class="list-group">
            <a href="#" class="list-group-item list-group-item-action active">Organization</a>
            <a href="#" class="list-group-item list-group-item-action">SMS Gateway</a>
            <a href="#" class="list-group-item list-group-item-action">Facebook Login</a>
            <a href="#" class="list-group-item list-group-item-action">GIS Settings</a>
            <a href="#" class="list-group-item list-group-item-action">System Backup</a>
            <a href="#" class="list-group-item list-group-item-action">Users</a>
            <a href="#" class="list-group-item list-group-item-action">Theme</a>
            <a href="#" class="list-group-item list-group-item-action">Security</a>
        </div>
    </div>
    <div class="col-md-9">
        <div class="card-flat p-4">
            <h6 class="fw-semibold mb-3">Organization</h6>
            <div class="mb-3"><label class="form-label small">Organization Name</label><input class="form-control" value="BFP Leyte Provincial Office"></div>
            <div class="mb-3"><label class="form-label small">Provincial Contact Number</label><input class="form-control" value="(053) 321-0000"></div>
            <div class="mb-3"><label class="form-label small">Coverage Area</label><input class="form-control" value="Province of Leyte"></div>
            <button class="btn btn-primary-fire btn-sm">Save Changes</button>
        </div>
    </div>
</div>
@endsection