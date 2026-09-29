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
        Schema::create('status_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->nullable()->constrained('status_service_groups')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();

            $table->string('url', 2048);
            $table->string('method', 10)->default('GET');
            $table->unsignedInteger('check_interval')->default(300);
            $table->unsignedInteger('timeout')->default(15);
            $table->unsignedInteger('connect_timeout')->default(5);

            $table->boolean('follow_redirects')->default(true);
            $table->unsignedTinyInteger('max_redirects')->default(5);
            $table->boolean('verify_ssl')->default(true);
            $table->string('http_version', 10)->default('auto');
            $table->string('user_agent')->nullable();

            $table->text('request_headers')->nullable()->comment('Encrypted name=>value map');
            $table->json('query_params')->nullable();
            $table->text('request_body')->nullable();
            $table->string('request_body_type', 20)->default('none');
            $table->text('authentication')->nullable()->comment('Encrypted auth config');

            $table->json('expected_status_codes')->nullable();
            $table->unsignedInteger('response_time_warning')->nullable();
            $table->unsignedInteger('response_time_failure')->nullable();
            $table->json('response_assertions')->nullable();
            $table->json('json_assertions')->nullable();
            $table->json('header_assertions')->nullable();

            $table->string('current_status', 20)->default('unknown');
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->timestamp('last_failure_at')->nullable();
            $table->timestamp('next_check_at')->nullable();

            $table->unsignedTinyInteger('failure_threshold')->default(3);
            $table->unsignedTinyInteger('recovery_threshold')->default(2);
            $table->boolean('auto_create_incidents')->default(true);
            $table->boolean('auto_resolve_incidents')->default(true);
            $table->boolean('notify_on_failure')->default(true);
            $table->boolean('notify_on_recovery')->default(true);

            $table->boolean('is_active')->default(true);
            $table->boolean('is_public')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('is_active');
            $table->index('next_check_at');
            $table->index('current_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('status_services');
    }
};
