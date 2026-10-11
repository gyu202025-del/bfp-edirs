
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            // Allow both old and new role values temporarily.
            DB::statement(
                "ALTER TABLE users MODIFY role
                ENUM('Administrator', 'BFP Personnel', 'Head', 'Citizen')
                NOT NULL DEFAULT 'Citizen'"
            );
        }

        // Convert existing BFP Personnel accounts to Head.
        DB::table('users')
            ->where('role', 'BFP Personnel')
            ->update(['role' => 'Head']);

        if ($driver === 'mysql') {
            // Remove the old role value from the ENUM.
            DB::statement(
                "ALTER TABLE users MODIFY role
                ENUM('Administrator', 'Head', 'Citizen')
                NOT NULL DEFAULT 'Citizen'"
            );
        }
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            // Temporarily allow both role values for rollback.
            DB::statement(
                "ALTER TABLE users MODIFY role
                ENUM('Administrator', 'BFP Personnel', 'Head', 'Citizen')
                NOT NULL DEFAULT 'Citizen'"
            );
        }

        // Restore the original role value.
        DB::table('users')
            ->where('role', 'Head')
            ->update(['role' => 'BFP Personnel']);

        if ($driver === 'mysql') {
            // Restore the original ENUM values.
            DB::statement(
                "ALTER TABLE users MODIFY role
                ENUM('Administrator', 'BFP Personnel', 'Citizen')
                NOT NULL DEFAULT 'Citizen'"
            );
        }
    }
};