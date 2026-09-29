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
        Schema::create('status_notification_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_id')->nullable()->constrained('status_notification_channels')->nullOnDelete();
            $table->string('event', 40);
            $table->foreignId('service_id')->nullable()->constrained('status_services')->nullOnDelete();
            // Recipient count + domains only (no raw emails) for privacy.
            $table->unsignedInteger('recipient_count')->default(0);
            $table->string('status', 20)->default('sent')->comment('sent|failed|skipped');
            $table->text('error')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('event');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('status_notification_deliveries');
    }
};
