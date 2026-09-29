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
        Schema::create('status_notification_channels', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type', 20)->comment('mail|webhook|telegram|discord|slack');
            // Encrypted at the model layer (tokens, webhook secrets), hence TEXT not JSON.
            $table->text('config')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('status_notification_channels');
    }
};
