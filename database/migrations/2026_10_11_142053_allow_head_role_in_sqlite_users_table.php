<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            Schema::table('users', function (Blueprint $table) {
                $table->string('role')
                    ->default('Citizen')
                    ->change();
            });
        }
    }

    public function down(): void
    {
        // Intentionally left empty.
        // Do not restore the restrictive role constraint.
    }
};