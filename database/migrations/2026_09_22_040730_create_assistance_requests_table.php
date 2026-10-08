<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assistance_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incident_id')->constrained()->cascadeOnDelete();
            $table->string('requesting_municipality');
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('target_station_id')->constrained('fire_stations')->cascadeOnDelete();
            $table->string('target_municipality');
            $table->enum('status', ['Pending', 'Approved', 'Declined'])->default('Pending');
            $table->foreignId('responded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistance_requests');
    }
};