<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

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

    public function isBfpPersonnel(): bool
    {
        return $this->role === 'BFP Personnel';
    }

    public function isCitizen(): bool
    {
        return $this->role === 'Citizen';
    }
}

