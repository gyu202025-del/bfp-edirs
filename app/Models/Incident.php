<?php
 
namespace App\Models;
 
use Illuminate\Database\Eloquent\Model;
 
class Incident extends Model
{
    protected $fillable = [
        'category', 'municipality', 'barangay', 'citizen_id', 'station_id', 'incident_type',
        'description', 'landmark', 'latitude', 'longitude', 'image',
        'status', 'reported_at', 'verified_at',
    ];
 
    protected function casts(): array
    {
        return [
            'reported_at' => 'datetime',
            'verified_at' => 'datetime',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }
 
    // Plain numbering: 1, 2, 3... (uses the table's auto-increment id)
    public function getDisplayNumberAttribute(): int
    {
        return $this->id;
    }
 
    // "San Sotero, Javier" instead of "10.7382, 124.9432"
    public function getLocationTextAttribute(): string
    {
        return collect([$this->barangay, $this->municipality])
            ->filter()
            ->implode(', ') ?: 'Unknown location';
    }
 
    public function citizen()
    {
        return $this->belongsTo(User::class, 'citizen_id');
    }
 
    public function dispatch()
    {
        return $this->hasOne(Dispatch::class);
    }
 
    public function attemptAutoDispatch(): bool
    {
        if ($this->status !== 'Pending' || !$this->latitude || !$this->longitude || $this->dispatch()->exists()) {
            return false;
        }
 
        $stations = \App\Models\FireStation::where('municipality', $this->municipality)
            ->where('status', 'Active')
            ->get();
 
        $nearest = $stations
            ->map(fn ($s) => ['station' => $s, 'distance' => $s->distanceTo($this->latitude, $this->longitude)])
            ->sortBy('distance')
            ->first();
 
        if (!$nearest) {
            return false;
        }
 
        \App\Models\Dispatch::create([
            'incident_id' => $this->id,
            'station_id' => $nearest['station']->id,
            'dispatcher_id' => null,
            'dispatch_time' => now(),
            'status' => 'Pending',
        ]);
 
        $this->update(['status' => 'Dispatched', 'verified_at' => now()]);
 
        return true;
    }
}