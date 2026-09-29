<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('status_subscribers', function (Blueprint $table) {
            // Persistent per-subscriber token: verification_token is consumed
            // on verify, but unsubscribe links must keep working afterwards.
            $table->string('unsubscribe_token', 64)->nullable()->unique()->after('verification_token');
        });

        foreach (DB::table('status_subscribers')->whereNull('unsubscribe_token')->select('id')->cursor() as $row) {
            DB::table('status_subscribers')->where('id', $row->id)->update([
                'unsubscribe_token' => Str::random(48),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('status_subscribers', function (Blueprint $table) {
            $table->dropColumn('unsubscribe_token');
        });
    }
};
