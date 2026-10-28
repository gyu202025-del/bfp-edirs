<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserManagementController extends Controller
{
    private function isAdmin(): bool
    {
        return auth()->user()->role === 'Administrator';
    }

    // Admin: everyone. Head: only Head accounts of own municipality.
    private function visibleQuery()
    {
        $me = auth()->user();
        $q = User::query();
        if (!$this->isAdmin()) {
            $q->where('role', 'Head')->where('municipality', $me->municipality);
        }
        return $q;
    }

    private function authorizeTarget(User $user): void
    {
        if ($this->isAdmin()) {
            return;
        }
        $me = auth()->user();
        abort_unless($user->role === 'Head' && $user->municipality === $me->municipality, 403);
    }

    public function index(Request $request)
    {
        $users = $this->visibleQuery()
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%' . $request->q . '%';
                $q->where(fn ($w) => $w->where('name', 'like', $term)->orWhere('email', 'like', $term));
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->orderBy('role')->orderBy('municipality')->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('users.index', [
            'users'          => $users,
            'isAdmin'        => $this->isAdmin(),
            'municipalities' => config('leyte.municipalities', []),
            'myMunicipality' => auth()->user()->municipality,
        ]);
    }

    public function store(Request $request)
    {
        $me = auth()->user();

        $rules = [
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|max:255|unique:users,email',
            'phone'    => 'nullable|string|max:30',
            'password' => 'required|string|min:8|max:100',
        ];
        if ($this->isAdmin()) {
            $rules['role']         = ['required', Rule::in(['Administrator', 'Head'])];
            $rules['municipality'] = 'required_if:role,Head|nullable|string|max:100';
        }
        $data = $request->validate($rules);

        if ($this->isAdmin()) {
            $role = $data['role'];
            $municipality = $role === 'Head' ? $data['municipality'] : null;
        } else {
            $role = 'Head';
            $municipality = $me->municipality;
        }

        User::create([
            'name'         => $data['name'],
            'email'        => $data['email'],
            'phone'        => $data['phone'] ?? null,
            'password'     => Hash::make($data['password']),
            'role'         => $role,
            'municipality' => $municipality,
            'status'       => 'Active',
        ]);

        return redirect()->route('users.index')->with('success', 'Account created for ' . $data['name'] . '.');
    }

    public function update(Request $request, User $user)
    {
        $this->authorizeTarget($user);
        $me = auth()->user();

        $rules = [
            'name'  => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => 'nullable|string|max:30',
        ];
        $canChangeRole = $this->isAdmin() && $user->id !== $me->id;
        if ($canChangeRole) {
            $rules['role']         = ['required', Rule::in(['Administrator', 'Head'])];
            $rules['municipality'] = 'required_if:role,Head|nullable|string|max:100';
        }
        $data = $request->validate($rules);

        $update = [
            'name'  => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
        ];

        if ($canChangeRole) {
            // Never leave the system without an active Administrator
            if ($user->role === 'Administrator' && $data['role'] !== 'Administrator') {
                $activeAdmins = User::where('role', 'Administrator')->where('status', 'Active')->count();
                if ($activeAdmins <= 1) {
                    return back()->withInput()->withErrors(['role' => 'There must be at least one active Administrator.']);
                }
            }
            $update['role'] = $data['role'];
            $update['municipality'] = $data['role'] === 'Head' ? $data['municipality'] : null;
        }

        $user->update($update);

        return redirect()->route('users.index')->with('success', 'Account updated.');
    }

    public function toggleStatus(User $user)
    {
        $this->authorizeTarget($user);

        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot deactivate your own account.');
        }

        $deactivating = $user->status === 'Active';

        if ($deactivating && $user->role === 'Administrator') {
            $activeAdmins = User::where('role', 'Administrator')->where('status', 'Active')->count();
            if ($activeAdmins <= 1) {
                return back()->with('error', 'There must be at least one active Administrator.');
            }
        }

        $user->update(['status' => $deactivating ? 'Inactive' : 'Active']);

        return back()->with('success', $user->name . ($deactivating ? ' was deactivated.' : ' was reactivated.'));
    }

    public function resetPassword(Request $request, User $user)
    {
        $this->authorizeTarget($user);

        $data = $request->validate([
            'password' => 'required|string|min:8|max:100',
        ]);

        $user->update(['password' => Hash::make($data['password'])]);

        return back()->with('success', 'Password reset for ' . $user->name . '. Give them the new temporary password.');
    }
}