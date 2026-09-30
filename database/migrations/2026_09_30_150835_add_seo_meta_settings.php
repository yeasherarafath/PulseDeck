<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * @var list<array{key: string, value: string|null, type: string, group: string, description: string}>
     */
    private const SETTINGS = [
        ['key' => 'meta_keywords', 'value' => null, 'type' => 'string', 'group' => 'general', 'description' => 'SEO meta keywords (comma-separated, optional).'],
        ['key' => 'og_image_path', 'value' => null, 'type' => 'string', 'group' => 'branding', 'description' => 'Social share image (og:image / twitter:image). Falls back to the logo.'],
        ['key' => 'meta_robots', 'value' => 'index, follow', 'type' => 'string', 'group' => 'public', 'description' => 'Robots meta for the public page (e.g. index, follow).'],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (self::SETTINGS as $setting) {
            DB::table('status_settings')->updateOrInsert(
                ['key' => $setting['key']],
                [
                    'value' => $setting['value'],
                    'type' => $setting['type'],
                    'group' => $setting['group'],
                    'is_encrypted' => false,
                    'description' => $setting['description'],
                    'updated_at' => now(),
                ],
            );

            Cache::forget('status-setting-v1:'.$setting['key']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('status_settings')
            ->whereIn('key', array_column(self::SETTINGS, 'key'))
            ->delete();

        foreach (self::SETTINGS as $setting) {
            Cache::forget('status-setting-v1:'.$setting['key']);
        }
    }
};
