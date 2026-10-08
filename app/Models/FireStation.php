<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FireStation extends Model
{
    protected $fillable = [
        'station_name', 'station_code', 'station_commander', 'address',
        'municipality', 'latitude', 'longitude', 'contact_number', 'status',
    ];

    /**
     * Straight-line distance in kilometers from this station to a point,
     * using the Haversine formula. Good enough for "nearest station" —
     * doesn't account for actual road routes, just as-the-crow-flies distance.
     */
    public function distanceTo(float $lat, float $lng): float
    {
        $earthRadiusKm = 6371;

        $latDelta = deg2rad($lat - $this->latitude);
        $lngDelta = deg2rad($lng - $this->longitude);

        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad($this->latitude)) * cos(deg2rad($lat))
            * sin($lngDelta / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadiusKm * $c;
    }
}