<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dispatches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incident_id')->constrained()->cascadeOnDelete();
            $table->foreignId('station_id')->nullable()->constrained('fire_stations')->nullOnDelete();
            $table->foreignId('dispatcher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('dispatch_time')->nullable();
            $table->timestamp('arrival_time')->nullable();
            $table->timestamp('completion_time')->nullable();
            $table->enum('status', ['Pending', 'Responding', 'Completed', 'Cancelled'])->default('Pending');
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispatches');
    }
};