<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssistanceRequest extends Model
{
    protected $fillable = [
        'incident_id', 'requesting_municipality', 'requested_by',
        'target_station_id', 'target_municipality', 'status',
        'responded_by', 'responded_at',
    ];

    protected function casts(): array
    {
        return ['responded_at' => 'datetime'];
    }

    public function incident() { return $this->belongsTo(Incident::class); }
    public function targetStation() { return $this->belongsTo(FireStation::class, 'target_station_id'); }
    public function requestedBy() { return $this->belongsTo(User::class, 'requested_by'); }
    public function respondedBy() { return $this->belongsTo(User::class, 'responded_by'); }
}