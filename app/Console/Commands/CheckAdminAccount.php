<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CheckAdminAccount extends Command
{
    protected $signature = 'app:check-admin';

    protected $description = 'Read-only check of the admin account';

    public function handle(): int
    {
        $connection = config('database.default');
        $database = config("database.connections.{$connection}.database");

        $this->info("Database connection: {$connection}");

        if ($connection === 'sqlite') {
            $this->info(
                'SQLite file exists: ' .
                (is_file($database) ? 'YES' : 'NO')
            );
        }

        $email = config('seed.admin_email');
        $user = User::where('email', $email)->first();

        $this->info('Admin account exists: ' . ($user ? 'YES' : 'NO'));

        if ($user) {
            $this->info('Account role: ' . $user->role);
            $this->info('Account status: ' . $user->status);

            $password = config('seed.password');

            $this->info(
                'Password matches SEED_PASSWORD: ' .
                ($password && Hash::check($password, $user->password)
                    ? 'YES'
                    : 'NO')
            );
        }

        return self::SUCCESS;
    }
}