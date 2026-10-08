<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Step 1: widen the enum so both old and new values are valid
        DB::statement("ALTER TABLE users MODIFY role ENUM('Administrator', 'BFP Personnel', 'Head', 'Citizen') NOT NULL DEFAULT 'Citizen'");

        // Step 2: convert existing data
        DB::table('users')->where('role', 'BFP Personnel')->update(['role' => 'Head']);

        // Step 3: narrow the enum, dropping the old value now that nothing uses it
        DB::statement("ALTER TABLE users MODIFY role ENUM('Administrator', 'Head', 'Citizen') NOT NULL DEFAULT 'Citizen'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE users MODIFY role ENUM('Administrator', 'BFP Personnel', 'Head', 'Citizen') NOT NULL DEFAULT 'Citizen'");
        DB::table('users')->where('role', 'Head')->update(['role' => 'BFP Personnel']);
        DB::statement("ALTER TABLE users MODIFY role ENUM('Administrator', 'BFP Personnel', 'Citizen') NOT NULL DEFAULT 'Citizen'");
    }
};