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
        Schema::create('status_daily_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained('status_services')->cascadeOnDelete();
            $table->date('date');

            $table->unsignedInteger('total_checks')->default(0);
            $table->unsignedInteger('successful_checks')->default(0);
            $table->unsignedInteger('failed_checks')->default(0);

            $table->decimal('uptime_percentage', 5, 2)->default(0);

            $table->unsignedInteger('avg_response_time')->nullable();
            $table->unsignedInteger('min_response_time')->nullable();
            $table->unsignedInteger('max_response_time')->nullable();

            $table->timestamps();

            $table->unique(['service_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('status_daily_stats');
    }
};
