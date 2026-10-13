<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateProductionAdmin extends Command
{
    protected $signature = 'app:create-production-admin';

    protected $description = 'Create the initial administrator if one does not exist';

    public function handle(): int
    {
        $email = config('seed.admin_email', 'admin@bfp.gov.ph');

        if (User::where('email', $email)->exists()) {
            $this->error('An account with this email already exists. No changes made.');
            return self::FAILURE;
        }

        $password = $this->secret('Enter the new admin password');

        if (! $password || strlen($password) < 12) {
            $this->error('Password must be at least 12 characters.');
            return self::FAILURE;
        }

        User::create([
            'name' => 'System Administrator',
            'email' => $email,
            'password' => Hash::make($password),
            'role' => 'Administrator',
            'municipality' => null,
            'status' => 'Active',
        ]);

        $this->info("Administrator created: {$email}");

        return self::SUCCESS;
    }
}