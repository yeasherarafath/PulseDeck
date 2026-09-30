<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * status:cleanup filters on these columns alone; the existing composite
     * indexes lead with another column and cannot serve those scans.
     */
    public function up(): void
    {
        Schema::table('status_daily_stats', fn (Blueprint $table) => $table->index('date'));
        Schema::table('status_audit_logs', fn (Blueprint $table) => $table->index('created_at'));
    }

    public function down(): void
    {
        Schema::table('status_daily_stats', fn (Blueprint $table) => $table->dropIndex(['date']));
        Schema::table('status_audit_logs', fn (Blueprint $table) => $table->dropIndex(['created_at']));
    }
};
