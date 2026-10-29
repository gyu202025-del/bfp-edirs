<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Notification extends Model
{
    public $timestamps = false; // only created_at, no updated_at, per your schema

    protected $fillable = ['user_id', 'incident_id', 'title', 'message', 'status'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function incident() { return $this->belongsTo(Incident::class); }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        $query->where('user_id', $user->id);
        if ($user->isAdministrator()) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($user) {
            $query->whereNull('incident_id');
            if ($user->department_id) {
                $query->orWhereHas('incident.assignments', fn (Builder $assignment) => $assignment
                    ->where('department_id', $user->department_id));
            }
        });
    }
}