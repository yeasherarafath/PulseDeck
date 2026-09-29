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
        Schema::create('status_incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->nullable()->constrained('status_services')->nullOnDelete();
            $table->string('title');
            $table->string('slug')->unique();

            $table->string('status', 20)->default('investigating');
            $table->string('impact', 20)->default('minor');

            $table->timestamp('started_at');
            $table->timestamp('resolved_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index('service_id');
            $table->index('status');
            $table->index('started_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('status_incidents');
    }
};
