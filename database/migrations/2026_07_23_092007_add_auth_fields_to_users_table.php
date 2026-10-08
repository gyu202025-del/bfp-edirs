<?php
// database/migrations/xxxx_xx_xx_add_auth_fields_to_users_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['Administrator', 'BFP Personnel', 'Citizen'])->default('Citizen')->after('email');
            $table->string('phone')->nullable()->after('role');
            $table->string('profile_picture')->nullable()->after('phone');
            $table->enum('status', ['Active', 'Inactive'])->default('Active')->after('profile_picture');
            $table->string('facebook_id')->nullable()->unique()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'phone', 'profile_picture', 'status', 'facebook_id']);
        });
    }
};