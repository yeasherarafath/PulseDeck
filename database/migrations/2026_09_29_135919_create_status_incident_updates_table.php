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
        Schema::create('status_incident_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incident_id')->constrained('status_incidents')->cascadeOnDelete();
            $table->string('status', 20);
            $table->text('message');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('status_incident_updates');
    }
};
