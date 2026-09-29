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
        Schema::create('status_notification_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_id')->constrained('status_notification_channels')->cascadeOnDelete();
            // Null service_id = rule applies to all services.
            $table->foreignId('service_id')->nullable()->constrained('status_services')->cascadeOnDelete();
            $table->string('event', 40);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('status_notification_rules');
    }
};
