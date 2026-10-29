<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserManagementController extends Controller
{
    private const MUNICIPAL_ROLES = ['Head', 'Staff'];

    private function isAdmin(): bool
    {
        return auth()->user()->role === 'Administrator';
    }

    private function roleOptions(): array
    {
        return $this->isAdmin() ? ['Administrator', 'Head', 'Staff'] : self::MUNICIPAL_ROLES;
    }

    // Admin: everyone. Head: Head and Staff accounts of own municipality only.
    private function visibleQuery()
    {
        $me = auth()->user();
        $q = User::query();
        if (!$this->isAdmin()) {
            $q->whereIn('role', self::MUNICIPAL_ROLES)->where('municipality', $me->municipality);
        }
        return $q;
    }

    private function authorizeTarget(User $user): void
    {
        if ($this->isAdmin()) {
            return;
        }
        $me = auth()->user();
        abort_unless(
            in_array($user->role, self::MUNICIPAL_ROLES, true) && $user->municipality === $me->municipality,
            403
        );
    }

    private function activeAdminCount(): int
    {
        return User::where('role', 'Administrator')->where('status', 'Active')->count();
    }

    public function index(Request $request)
    {
        // Only the Administrator can look at deleted accounts
        $showDeleted = $this->isAdmin() && $request->boolean('deleted');

        $query = $this->visibleQuery();
        if ($showDeleted) {
            $query->onlyTrashed();
        }

        $users = $query
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%' . $request->q . '%';
                $q->where(fn ($w) => $w->where('name', 'like', $term)->orWhere('email', 'like', $term));
            })
            ->when(!$showDeleted && $request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->with(['departmentRecord', 'roleRecord'])
            ->orderBy('role')->orderBy('municipality')->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('users.index', [
            'users'          => $users,
            'isAdmin'        => $this->isAdmin(),
            'showDeleted'    => $showDeleted,
            'roleOptions'    => $this->roleOptions(),
            'departmentOptions' => \App\Support\DepartmentRouting::options(),
            'municipalities' => config('leyte.municipalities', []),
            'myMunicipality' => auth()->user()->municipality,
        ]);
    }

    public function store(Request $request)
    {
        $me = auth()->user();

        $rules = [
            'first_name' => 'required|string|max:100',
            'middle_initial' => 'nullable|string|max:5',
            'surname' => 'required|string|max:100',
            'email'    => 'required|email|max:255|unique:users,email',
            'phone'    => 'nullable|string|max:30',
            'password' => 'required|string|min:8|max:100',
            'role'     => ['required', Rule::in($this->roleOptions())],
        ];
        if ($this->isAdmin()) {
            $rules['department'] = [
                Rule::requiredIf(fn () => in_array($request->input('role'), self::MUNICIPAL_ROLES, true)),
                'nullable', Rule::in(\App\Support\DepartmentRouting::options()),
            ];
            $rules['municipality'] = [
                Rule::requiredIf(fn () => in_array($request->input('role'), self::MUNICIPAL_ROLES, true)),
                'nullable', 'string', 'max:100',
            ];
        }
        $data = $request->validate($rules);

        $role = $data['role'];
        $departmentId = $this->isAdmin()
            ? (in_array($role, self::MUNICIPAL_ROLES, true) ? Department::where('name', $data['department'])->value('id') : null)
            : $me->department_id;
        if (in_array($role, self::MUNICIPAL_ROLES, true) && !$departmentId) {
            return back()->withInput()->withErrors(['department' => 'Assign a department to your account before creating department accounts.']);
        }
        if ($this->isAdmin()) {
            $municipality = in_array($role, self::MUNICIPAL_ROLES, true) ? $data['municipality'] : null;
        } else {
            $municipality = $me->municipality;
        }
        $name = $this->formatName($data['first_name'], $data['middle_initial'] ?? null, $data['surname']);

        User::create([
            'name'         => $name,
            'first_name'   => $data['first_name'],
            'middle_initial' => $data['middle_initial'] ?? null,
            'surname'      => $data['surname'],
            'email'        => $data['email'],
            'phone'        => $data['phone'] ?? null,
            'department_id' => $departmentId,
            'password'     => Hash::make($data['password']),
            'role'         => $role,
            'municipality' => $municipality,
            'status'       => 'Active',
        ]);

        return redirect()->route('users.index')->with('success', 'Account created for ' . $name . ' (' . $role . ').');
    }

    public function update(Request $request, User $user)
    {
        $this->authorizeTarget($user);
        $me = auth()->user();

        $rules = [
            'name'  => 'nullable|required_without:first_name|string|max:255',
            'first_name' => 'nullable|required_with:surname|string|max:100',
            'middle_initial' => 'nullable|string|max:5',
            'surname' => 'nullable|required_with:first_name|string|max:100',
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => 'nullable|string|max:30',
        ];
        $canChangeRole = $this->isAdmin() && $user->id !== $me->id;
        if ($canChangeRole) {
            $rules['role'] = ['required', Rule::in(['Administrator', 'Head', 'Staff', 'Citizen'])];
            $rules['department'] = [
                Rule::requiredIf(fn () => in_array($request->input('role'), self::MUNICIPAL_ROLES, true)),
                'nullable', Rule::in(\App\Support\DepartmentRouting::options()),
            ];
            $rules['municipality'] = [
                Rule::requiredIf(fn () => in_array($request->input('role'), self::MUNICIPAL_ROLES, true)),
                'nullable', 'string', 'max:100',
            ];
        }
        $data = $request->validate($rules);

        $update = [
            'name'  => isset($data['first_name'])
                ? $this->formatName($data['first_name'], $data['middle_initial'] ?? null, $data['surname'])
                : $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
        ];

        if (isset($data['first_name'])) {
            $update['first_name'] = $data['first_name'];
            $update['middle_initial'] = $data['middle_initial'] ?? null;
            $update['surname'] = $data['surname'];
        }

        if ($canChangeRole) {
            // Never leave the system without an active Administrator
            if ($user->role === 'Administrator' && $data['role'] !== 'Administrator' && $this->activeAdminCount() <= 1) {
                return back()->withInput()->withErrors(['role' => 'There must be at least one active Administrator.']);
            }
            $update['role'] = $data['role'];
            $update['municipality'] = in_array($data['role'], self::MUNICIPAL_ROLES, true) ? $data['municipality'] : null;
            $update['department_id'] = in_array($data['role'], self::MUNICIPAL_ROLES, true)
                ? Department::where('name', $data['department'])->value('id')
                : null;
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

        if ($deactivating && $user->role === 'Administrator' && $this->activeAdminCount() <= 1) {
            return back()->with('error', 'There must be at least one active Administrator.');
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

    // Soft delete: hidden from the list and cannot log in, but history stays intact
    public function destroy(User $user)
    {
        $this->authorizeTarget($user);

        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        if ($user->role === 'Administrator' && $user->status === 'Active' && $this->activeAdminCount() <= 1) {
            return back()->with('error', 'There must be at least one active Administrator.');
        }

        $name = $user->name;
        $user->delete();

        return redirect()->route('users.index')->with('success', $name . ' was deleted.');
    }

    // Administrator only
    public function restore($id)
    {
        abort_unless($this->isAdmin(), 403);

        $user = User::onlyTrashed()->findOrFail($id);
        $user->restore();

        return redirect()->route('users.index', ['deleted' => 1])->with('success', $user->name . ' was restored.');
    }

    private function formatName(string $firstName, ?string $middleInitial, string $surname): string
    {
        return implode(' ', array_filter([
            trim($firstName),
            $middleInitial ? trim($middleInitial) : null,
            trim($surname),
        ]));
    }
}