<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'first_name',
        'middle_initial',
        'surname',
        'email',
        'password',
        'role',
        'municipality',
        'department_id',
        'phone',
        'profile_picture',
        'status',
        'facebook_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (User $user) {
            if ($user->role && ($user->isDirty('role') || !$user->role_id)) {
                $user->role_id = Role::where('name', $user->role)->value('id');
            }
        });
    }

    public function roleRecord(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function departmentRecord(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function getDepartmentAttribute(): ?string
    {
        return $this->departmentRecord?->name;
    }

    public function hasDepartment(string $name): bool
    {
        return $this->departmentRecord?->name === $name;
    }

    public function isAdministrator(): bool
    {
        return $this->roleRecord?->scope === 'system' && $this->role === 'Administrator';
    }

    // "BFP Personnel" no longer exists as a role; kept so older code calling it still works
    public function isBfpPersonnel(): bool
    {
        return $this->isMunicipalStaff() && $this->hasDepartment('BFP');
    }

    public function isMunicipalStaff(): bool
    {
        return in_array($this->role, ['Head', 'Staff'], true);
    }

    public function isCitizen(): bool
    {
        return $this->role === 'Citizen';
    }
}