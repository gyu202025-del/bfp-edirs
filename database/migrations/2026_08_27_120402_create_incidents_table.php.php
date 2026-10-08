<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->string('incident_number')->unique();
            $table->foreignId('citizen_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('station_id')->nullable()->constrained('fire_stations')->nullOnDelete();
            $table->string('incident_type')->nullable();
            $table->text('description')->nullable();
            $table->string('landmark')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('image')->nullable();
            $table->enum('report_source', ['Mobile App', 'SMS'])->default('Mobile App');
            $table->enum('status', ['Pending', 'Verified', 'Dispatched', 'Responding', 'Resolved', 'Cancelled'])->default('Pending');
            $table->timestamp('reported_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incidents');
    }
};