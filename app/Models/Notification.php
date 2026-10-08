<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    public $timestamps = false; // only created_at, no updated_at, per your schema

    protected $fillable = ['user_id', 'incident_id', 'title', 'message', 'status'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function incident() { return $this->belongsTo(Incident::class); }
}