<?php

namespace Database\Seeders;

use App\Models\FireStation;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $password = config('seed.password');

        if (! $password) {
            $this->command->warn('SEED_PASSWORD is not set. Skipping accounts and stations.');
            return;
        }

        // Province-wide Administrator
        User::updateOrCreate(
            ['email' => config('seed.admin_email')],
            [
                'name' => 'System Administrator',
                'password' => $password,
                'role' => 'Administrator',
                'municipality' => null,
                'status' => 'Active',
            ]
        );

        // One Head account per municipality
        $heads = [
            ['Head Officer Abuyog', 'abuyog@bfp.gov.ph', 'Abuyog'],
            ['Head Officer MacArthur', 'macarthur@bfp.gov.ph', 'MacArthur'],
            ['Head Officer Javier', 'javier@bfp.gov.ph', 'Javier'],
        ];

        foreach ($heads as [$name, $email, $municipality]) {
            User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => $password,
                    'role' => 'Head',
                    'municipality' => $municipality,
                    'status' => 'Active',
                ]
            );
        }

        // Fire stations. These coordinates are municipal centers.
        // Replace them with each station's real location when you have it.
        $stations = [
            ['FS-ABY', 'Abuyog Fire Station', 'Abuyog', 10.7480, 125.0115],
            ['FS-MAC', 'MacArthur Fire Station', 'MacArthur', 10.8347, 124.9963],
            ['FS-JAV', 'Javier Fire Station', 'Javier', 10.7941, 124.9361],
        ];

        foreach ($stations as [$code, $name, $municipality, $lat, $lng]) {
            FireStation::updateOrCreate(
                ['station_code' => $code],
                [
                    'station_name' => $name,
                    'municipality' => $municipality,
                    'latitude' => $lat,
                    'longitude' => $lng,
                    'status' => 'Active',
                ]
            );
        }
    }
}