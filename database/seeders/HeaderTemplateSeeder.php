<?php

namespace Database\Seeders;

use App\Models\Status\StatusHeaderTemplate;
use Illuminate\Database\Seeder;

class HeaderTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'name' => 'JSON API',
                'description' => 'Standard JSON API request.',
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'User-Agent' => 'StatusMonitor/1.0',
                ],
            ],
            [
                'name' => 'Browser Request',
                'description' => 'Mimics a regular browser visit.',
                'headers' => [
                    'Accept' => 'text/html',
                    'User-Agent' => 'Mozilla/5.0 (StatusMonitor)',
                ],
            ],
            [
                'name' => 'Internal API',
                'description' => 'Internal services with request correlation.',
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'X-Requested-With' => 'StatusMonitor',
                    'User-Agent' => 'StatusMonitor/1.0',
                ],
            ],
        ];

        foreach ($templates as $template) {
            StatusHeaderTemplate::updateOrCreate(
                ['name' => $template['name']],
                [
                    'description' => $template['description'],
                    'headers' => $template['headers'],
                    'is_active' => true,
                ],
            );
        }
    }
}
