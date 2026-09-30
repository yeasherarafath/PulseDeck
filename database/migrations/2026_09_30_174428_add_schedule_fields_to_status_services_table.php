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
            $table->string('schedule_type', 10)->default('interval')->after('check_interval');
            $table->string('cron_expression', 100)->nullable()->after('schedule_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('status_services', function (Blueprint $table) {
            $table->dropColumn(['schedule_type', 'cron_expression']);
        });
    }
};
