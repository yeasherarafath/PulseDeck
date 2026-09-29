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
        Schema::create('status_header_presets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('header_name');
            $table->string('description')->nullable();
            $table->string('category', 20)->default('common');
            $table->string('input_type', 20)->default('text')->comment('text|password|select');
            $table->json('options')->nullable()->comment('Predefined values for select inputs');
            $table->boolean('is_sensitive')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('status_header_presets');
    }
};
