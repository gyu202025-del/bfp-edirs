<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fire_stations', function (Blueprint $table) {
            $table->id();
            $table->string('station_name');
            $table->string('station_code')->unique();
            $table->string('station_commander')->nullable();
            $table->string('address')->nullable();
            $table->string('municipality')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('contact_number')->nullable();
            $table->enum('status', ['Active', 'Inactive'])->default('Active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fire_stations');
    }
};