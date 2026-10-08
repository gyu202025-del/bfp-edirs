<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Dispatch extends Model
{
    protected $fillable = [
        'incident_id', 'station_id', 'dispatcher_id',
        'dispatch_time', 'arrival_time', 'completion_time', 'status', 'remarks',
    ];

    protected function casts(): array
    {
        return [
            'dispatch_time' => 'datetime',
            'arrival_time' => 'datetime',
            'completion_time' => 'datetime',
        ];
    }

    public function incident() { return $this->belongsTo(Incident::class); }
    public function station() { return $this->belongsTo(FireStation::class, 'station_id'); }
    public function dispatcher() { return $this->belongsTo(User::class, 'dispatcher_id'); }
}