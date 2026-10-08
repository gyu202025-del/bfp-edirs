<?php
// database/migrations/xxxx_xx_xx_update_incidents_for_categories.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            $table->enum('category', ['Fire', 'Medical', 'Crime/Security', 'Accident'])->after('incident_number');
            $table->string('reporter_name')->nullable()->after('citizen_id');
            $table->string('reporter_contact')->nullable()->after('reporter_name');
            $table->foreignId('citizen_id')->nullable()->change(); // no account required anymore
        });
    }

    public function down(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            $table->dropColumn(['category', 'reporter_name', 'reporter_contact']);
        });
    }
};