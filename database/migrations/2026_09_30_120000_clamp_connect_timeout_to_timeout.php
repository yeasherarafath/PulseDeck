<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The request now requires connect_timeout <= timeout; repair older rows
     * so editing them does not fail on a field the user never touched.
     */
    public function up(): void
    {
        DB::table('status_services')->whereColumn('connect_timeout', '>', 'timeout')
            ->update(['connect_timeout' => DB::raw('timeout')]);
    }

    public function down(): void
    {
        //
    }
};
