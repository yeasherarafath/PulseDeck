<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('status_maintenance_service', function (Blueprint $table) {
            $table->foreignId('maintenance_id')->constrained('status_maintenances')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained('status_services')->cascadeOnDelete();

            $table->unique(['maintenance_id', 'service_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('status_maintenance_service');
    }
};
