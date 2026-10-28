<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'municipality',
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

    public function isAdministrator(): bool
    {
        return $this->role === 'Administrator';
    }

    // "BFP Personnel" no longer exists as a role; kept so older code calling it still works
    public function isBfpPersonnel(): bool
    {
        return in_array($this->role, ['Head', 'Staff'], true);
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