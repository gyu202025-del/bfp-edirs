<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }
        DB::statement("ALTER TABLE users MODIFY role ENUM('Administrator', 'Head', 'Staff', 'Citizen') NOT NULL DEFAULT 'Citizen'");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }
        DB::table('users')->where('role', 'Staff')->update(['role' => 'Head']);
        DB::statement("ALTER TABLE users MODIFY role ENUM('Administrator', 'Head', 'Citizen') NOT NULL DEFAULT 'Citizen'");
    }
};