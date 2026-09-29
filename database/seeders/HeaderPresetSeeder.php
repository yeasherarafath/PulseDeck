<?php

namespace Database\Seeders;

use App\Enums\Status\HeaderPresetCategory;
use App\Models\Status\StatusHeaderPreset;
use Illuminate\Database\Seeder;

class HeaderPresetSeeder extends Seeder
{
    /**
     * @var list<array{name: string, header: string, category: HeaderPresetCategory, type: string, sensitive: bool, options: list<string>|null, description: string|null}>
     */
    private const PRESETS = [
        ['name' => 'Accept', 'header' => 'Accept', 'category' => HeaderPresetCategory::Common, 'type' => 'select', 'sensitive' => false, 'options' => ['application/json', 'text/html', 'application/xml', '*/*'], 'description' => 'Media types the client accepts.'],
        ['name' => 'Accept-Language', 'header' => 'Accept-Language', 'category' => HeaderPresetCategory::Common, 'type' => 'text', 'sensitive' => false, 'options' => null, 'description' => 'Preferred response language.'],
        ['name' => 'Authorization', 'header' => 'Authorization', 'category' => HeaderPresetCategory::Common, 'type' => 'password', 'sensitive' => true, 'options' => null, 'description' => 'Credentials (prefer the Auth tab instead of raw headers).'],
        ['name' => 'Cache-Control', 'header' => 'Cache-Control', 'category' => HeaderPresetCategory::Common, 'type' => 'text', 'sensitive' => false, 'options' => null, 'description' => 'Caching directives.'],
        ['name' => 'Content-Type', 'header' => 'Content-Type', 'category' => HeaderPresetCategory::Common, 'type' => 'select', 'sensitive' => false, 'options' => ['application/json', 'application/x-www-form-urlencoded', 'multipart/form-data', 'text/plain', 'application/xml', 'text/xml'], 'description' => 'Body media type.'],
        ['name' => 'Cookie', 'header' => 'Cookie', 'category' => HeaderPresetCategory::Common, 'type' => 'password', 'sensitive' => true, 'options' => null, 'description' => 'Stored cookies. Treated as sensitive.'],
        ['name' => 'Origin', 'header' => 'Origin', 'category' => HeaderPresetCategory::Common, 'type' => 'text', 'sensitive' => false, 'options' => null, 'description' => 'Request origin for CORS.'],
        ['name' => 'Referer', 'header' => 'Referer', 'category' => HeaderPresetCategory::Common, 'type' => 'text', 'sensitive' => false, 'options' => null, 'description' => 'Referring page address.'],
        ['name' => 'User-Agent', 'header' => 'User-Agent', 'category' => HeaderPresetCategory::Common, 'type' => 'text', 'sensitive' => false, 'options' => null, 'description' => 'Client identifier.'],
        ['name' => 'X-API-Key', 'header' => 'X-API-Key', 'category' => HeaderPresetCategory::Api, 'type' => 'password', 'sensitive' => true, 'options' => null, 'description' => 'API key (prefer the Auth tab).'],
        ['name' => 'X-Auth-Token', 'header' => 'X-Auth-Token', 'category' => HeaderPresetCategory::Api, 'type' => 'password', 'sensitive' => true, 'options' => null, 'description' => 'Auth token. Treated as sensitive.'],
        ['name' => 'X-Access-Token', 'header' => 'X-Access-Token', 'category' => HeaderPresetCategory::Api, 'type' => 'password', 'sensitive' => true, 'options' => null, 'description' => 'Access token. Treated as sensitive.'],
        ['name' => 'X-Client-ID', 'header' => 'X-Client-ID', 'category' => HeaderPresetCategory::Api, 'type' => 'text', 'sensitive' => false, 'options' => null, 'description' => 'Client identifier for APIs.'],
        ['name' => 'X-Request-ID', 'header' => 'X-Request-ID', 'category' => HeaderPresetCategory::Api, 'type' => 'text', 'sensitive' => false, 'options' => null, 'description' => 'Request correlation ID.'],
        ['name' => 'X-Correlation-ID', 'header' => 'X-Correlation-ID', 'category' => HeaderPresetCategory::Api, 'type' => 'text', 'sensitive' => false, 'options' => null, 'description' => 'Distributed tracing ID.'],
        ['name' => 'X-Requested-With', 'header' => 'X-Requested-With', 'category' => HeaderPresetCategory::Api, 'type' => 'text', 'sensitive' => false, 'options' => null, 'description' => 'AJAX request marker.'],
    ];

    public function run(): void
    {
        foreach (self::PRESETS as $order => $preset) {
            StatusHeaderPreset::updateOrCreate(
                ['header_name' => $preset['header']],
                [
                    'name' => $preset['name'],
                    'description' => $preset['description'],
                    'category' => $preset['category'],
                    'input_type' => $preset['type'],
                    'options' => $preset['options'],
                    'is_sensitive' => $preset['sensitive'],
                    'is_active' => true,
                    'sort_order' => $order,
                ],
            );
        }
    }
}
