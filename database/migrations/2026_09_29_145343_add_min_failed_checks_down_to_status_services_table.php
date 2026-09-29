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
        Schema::table('status_services', function (Blueprint $table) {
            // Per-service override. Null = fall back to the global
            // min_failed_checks_down setting (default 1).
            $table->unsignedTinyInteger('min_failed_checks_down')->nullable()->after('recovery_threshold');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('status_services', function (Blueprint $table) {
            $table->dropColumn('min_failed_checks_down');
        });
    }
};
