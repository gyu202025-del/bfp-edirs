@extends('layouts.app')

@section('title', 'User Management')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0">User Management</h4>
        <small class="text-muted">
            {{ $isAdmin ? 'All accounts' : 'Head accounts in ' . $myMunicipality }}
        </small>
    </div>
    <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#createModal">+ New Account</button>
</div>

@if(session('success')) <div class="alert alert-success py-2">{{ session('success') }}</div> @endif
@if(session('error'))   <div class="alert alert-danger py-2">{{ session('error') }}</div> @endif
@if($errors->any())
    <div class="alert alert-danger py-2">
        <ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif

{{-- Search --}}
<form method="GET" class="card card-body mb-3">
    <div class="row g-2">
        <div class="col-12 col-md-6">
            <input type="text" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Search name or email">
        </div>
        <div class="col-6 col-md-3">
            <select name="status" class="form-select form-select-sm">
                <option value="">All status</option>
                <option value="Active" @selected(request('status') === 'Active')>Active</option>
                <option value="Inactive" @selected(request('status') === 'Inactive')>Inactive</option>
            </select>
        </div>
        <div class="col-6 col-md-3 d-flex gap-1">
            <button class="btn btn-sm btn-primary flex-fill">Search</button>
            <a href="{{ route('users.index') }}" class="btn btn-sm btn-outline-secondary flex-fill">Reset</a>
        </div>
    </div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-sm table-striped align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Name</th><th>Email</th><th>Role</th><th>Municipality</th>
                    <th>Phone</th><th>Status</th><th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $u)
                    @php $isSelf = $u->id === auth()->id(); @endphp
                    <tr>
                        <td>{{ $u->name }} @if($isSelf)<span class="badge bg-secondary">You</span>@endif</td>
                        <td>{{ $u->email }}</td>
                        <td><span class="badge {{ $u->role === 'Administrator' ? 'bg-dark' : 'bg-primary' }}">{{ $u->role }}</span></td>
                        <td>{{ $u->municipality ?? '—' }}</td>
                        <td>{{ $u->phone ?? '—' }}</td>
                        <td>
                            <span class="badge {{ $u->status === 'Active' ? 'bg-success' : 'bg-secondary' }}">{{ $u->status }}</span>
                        </td>
                        <td class="text-end text-nowrap">
                            <button class="btn btn-sm btn-outline-primary edit-btn"
                                    data-id="{{ $u->id }}" data-name="{{ $u->name }}" data-email="{{ $u->email }}"
                                    data-phone="{{ $u->phone }}" data-role="{{ $u->role }}"
                                    data-municipality="{{ $u->municipality }}" data-self="{{ $isSelf ? 1 : 0 }}">Edit</button>
                            <button class="btn btn-sm btn-outline-warning reset-btn"
                                    data-id="{{ $u->id }}" data-name="{{ $u->name }}">Reset Password</button>
                            @unless($isSelf)
                                <form method="POST" action="{{ route('users.toggle', $u) }}" class="d-inline"
                                      onsubmit="return confirm('{{ $u->status === 'Active' ? 'Deactivate' : 'Reactivate' }} {{ addslashes($u->name) }}?')">
                                    @csrf
                                    <button class="btn btn-sm {{ $u->status === 'Active' ? 'btn-outline-danger' : 'btn-outline-success' }}">
                                        {{ $u->status === 'Active' ? 'Deactivate' : 'Reactivate' }}
                                    </button>
                                </form>
                            @endunless
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">No accounts found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white">{{ $users->links() }}</div>
</div>

{{-- Create modal --}}
<div class="modal fade" id="createModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('users.store') }}" class="modal-content">
            @csrf
            <input type="hidden" name="_form" value="create">
            <div class="modal-header"><h5 class="modal-title">New Account</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-2"><label class="form-label small">Full name *</label>
                    <input name="name" class="form-control" value="{{ old('_form') === 'create' ? old('name') : '' }}" required></div>
                <div class="mb-2"><label class="form-label small">Email *</label>
                    <input type="email" name="email" class="form-control" value="{{ old('_form') === 'create' ? old('email') : '' }}" required></div>
                <div class="mb-2"><label class="form-label small">Phone</label>
                    <input name="phone" class="form-control" value="{{ old('_form') === 'create' ? old('phone') : '' }}"></div>

                @if($isAdmin)
                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label class="form-label small">Role *</label>
                            <select name="role" id="createRole" class="form-select">
                                <option value="Head" @selected(old('role') !== 'Administrator')>Head</option>
                                <option value="Administrator" @selected(old('role') === 'Administrator')>Administrator</option>
                            </select>
                        </div>
                        <div class="col-6" id="createMuniWrap">
                            <label class="form-label small">Municipality *</label>
                            <select name="municipality" class="form-select">
                                <option value="">Select...</option>
                                @foreach($municipalities as $m)
                                    <option value="{{ $m }}" @selected(old('_form') === 'create' && old('municipality') === $m)>{{ $m }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                @else
                    <div class="mb-2 small text-muted">
                        Role: <strong>Head</strong> &nbsp;|&nbsp; Municipality: <strong>{{ $myMunicipality }}</strong>
                    </div>
                @endif

                <div class="mb-2">
                    <label class="form-label small">Temporary password * (min. 8 characters)</label>
                    <div class="input-group">
                        <input type="password" name="password" id="createPassword" class="form-control" minlength="8" required>
                        <button type="button" class="btn btn-outline-secondary toggle-pw" data-target="createPassword">Show</button>
                    </div>
                    <div class="form-text">Give this password to the user. Tell them to keep it private.</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-danger">Create Account</button>
            </div>
        </form>
    </div>
</div>

{{-- Edit modal --}}
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" id="editForm" class="modal-content">
            @csrf @method('PUT')
            <input type="hidden" name="_form" value="edit">
            <input type="hidden" name="_id" id="editId" value="{{ old('_id') }}">
            <div class="modal-header"><h5 class="modal-title">Edit Account</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-2"><label class="form-label small">Full name *</label>
                    <input name="name" id="editName" class="form-control" required></div>
                <div class="mb-2"><label class="form-label small">Email *</label>
                    <input type="email" name="email" id="editEmail" class="form-control" required></div>
                <div class="mb-2"><label class="form-label small">Phone</label>
                    <input name="phone" id="editPhone" class="form-control"></div>

                @if($isAdmin)
                    <div class="row g-2 mb-2" id="editRoleRow">
                        <div class="col-6">
                            <label class="form-label small">Role *</label>
                            <select name="role" id="editRole" class="form-select">
                                <option value="Head">Head</option>
                                <option value="Administrator">Administrator</option>
                            </select>
                        </div>
                        <div class="col-6" id="editMuniWrap">
                            <label class="form-label small">Municipality *</label>
                            <select name="municipality" id="editMunicipality" class="form-select">
                                <option value="">Select...</option>
                                @foreach($municipalities as $m)
                                    <option value="{{ $m }}">{{ $m }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="form-text d-none" id="selfNote">You cannot change your own role or municipality.</div>
                @endif
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

{{-- Reset password modal --}}
<div class="modal fade" id="resetModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" id="resetForm" class="modal-content">
            @csrf
            <div class="modal-header"><h5 class="modal-title">Reset Password</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <p class="mb-2">New temporary password for <strong id="resetName"></strong></p>
                <div class="input-group">
                    <input type="password" name="password" id="resetPassword" class="form-control" minlength="8" required>
                    <button type="button" class="btn btn-outline-secondary toggle-pw" data-target="resetPassword">Show</button>
                </div>
                <div class="form-text">Minimum 8 characters. Give it to the user personally.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-warning">Reset Password</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const updateUrl = @json(route('users.update', ['user' => '__ID__']));
    const resetUrl  = @json(route('users.resetPassword', ['user' => '__ID__']));
    const isAdmin   = @json($isAdmin);
    const oldInput  = @json(old());

    const modal = id => bootstrap.Modal.getOrCreateInstance(document.getElementById(id));

    // Show/hide password
    document.querySelectorAll('.toggle-pw').forEach(btn => btn.addEventListener('click', () => {
        const input = document.getElementById(btn.dataset.target);
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        btn.textContent = show ? 'Hide' : 'Show';
    }));

    // Municipality only matters for Head
    function bindRole(roleId, wrapId) {
        const role = document.getElementById(roleId), wrap = document.getElementById(wrapId);
        if (!role || !wrap) return;
        const sync = () => wrap.style.display = role.value === 'Head' ? '' : 'none';
        role.addEventListener('change', sync);
        sync();
    }
    bindRole('createRole', 'createMuniWrap');
    bindRole('editRole', 'editMuniWrap');

    function openEdit(d) {
        document.getElementById('editForm').action = updateUrl.replace('__ID__', d.id);
        document.getElementById('editId').value = d.id;
        document.getElementById('editName').value = d.name ?? '';
        document.getElementById('editEmail').value = d.email ?? '';
        document.getElementById('editPhone').value = d.phone ?? '';
        if (isAdmin) {
            const role = document.getElementById('editRole'), muni = document.getElementById('editMunicipality');
            role.value = d.role || 'Head';
            muni.value = d.municipality ?? '';
            const self = String(d.self) === '1';
            role.disabled = self;
            muni.disabled = self;
            document.getElementById('selfNote').classList.toggle('d-none', !self);
            role.dispatchEvent(new Event('change'));
        }
        modal('editModal').show();
    }

    document.querySelectorAll('.edit-btn').forEach(btn => btn.addEventListener('click', () => openEdit(btn.dataset)));

    document.querySelectorAll('.reset-btn').forEach(btn => btn.addEventListener('click', () => {
        document.getElementById('resetForm').action = resetUrl.replace('__ID__', btn.dataset.id);
        document.getElementById('resetName').textContent = btn.dataset.name;
        document.getElementById('resetPassword').value = '';
        modal('resetModal').show();
    }));

    // Re-open the modal after a validation error
    if (oldInput && oldInput._form === 'create') {
        modal('createModal').show();
    } else if (oldInput && oldInput._form === 'edit' && oldInput._id) {
        const row = document.querySelector('.edit-btn[data-id="' + oldInput._id + '"]');
        openEdit({
            id: oldInput._id,
            name: oldInput.name, email: oldInput.email, phone: oldInput.phone,
            role: oldInput.role ?? (row ? row.dataset.role : 'Head'),
            municipality: oldInput.municipality ?? (row ? row.dataset.municipality : ''),
            self: row ? row.dataset.self : 0,
        });
    }
</script>
@endpush