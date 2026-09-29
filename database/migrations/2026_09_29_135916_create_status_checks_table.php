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
        Schema::create('status_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained('status_services')->cascadeOnDelete();

            $table->boolean('success');
            $table->string('status', 20);

            $table->unsignedSmallInteger('http_status')->nullable();
            $table->unsignedInteger('response_time')->nullable()->comment('Total time in ms');
            $table->unsignedInteger('connect_time')->nullable()->comment('Connect time in ms');

            $table->string('final_url', 2048)->nullable();
            $table->unsignedTinyInteger('redirect_count')->default(0);
            $table->unsignedBigInteger('response_size')->nullable();

            $table->string('error_type', 30)->nullable();
            $table->text('error_message')->nullable();

            // No raw response bodies by default: status, timings, assertion results only.
            $table->json('assertion_result')->nullable();
            $table->timestamp('checked_at');
            $table->timestamps();

            $table->index('service_id');
            $table->index('checked_at');
            $table->index(['service_id', 'checked_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('status_checks');
    }
};
