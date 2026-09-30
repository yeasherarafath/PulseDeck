<?php

namespace App\Http\Controllers\Admin\Status;

use App\Enums\Status\IncidentImpact;
use App\Enums\Status\IncidentStatus;
use App\Enums\Status\ServiceStatus;
use App\Http\Controllers\Controller;
use App\Models\Status\StatusService;
use Illuminate\View\View;

/**
 * Admin-facing reference for the public, read-only JSON API.
 */
class ApiDocsController extends Controller
{
    public function __invoke(): View
    {
        $base = rtrim(url('/api/status'), '/');

        return view('admin.status.api-docs.index', [
            'base' => $base,
            'origin' => rtrim(url('/'), '/'),
            'apiEnabled' => (bool) setting('public_api_enabled', true),
            'badgeEnabled' => (bool) setting('badge_enabled', true),
            'endpoints' => $this->endpoints($base),
            'statuses' => array_map(fn (ServiceStatus $status) => [$status->value, $status->label()], ServiceStatus::cases()),
            'incidentStatuses' => array_column(IncidentStatus::cases(), 'value'),
            'impacts' => array_column(IncidentImpact::cases(), 'value'),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function endpoints(string $base): array
    {
        // Prefer a real public service so "Try it" returns live data.
        $slug = StatusService::public()->orderBy('sort_order')->orderBy('name')->value('slug') ?? 'main-website';

        return [
            [
                'id' => 'overall',
                'title' => 'Overall status',
                'method' => 'GET',
                'path' => '/api/status',
                'url' => $base,
                'description' => 'Aggregated state of every public service plus a compact list of services. Use it for dashboards and uptime widgets.',
                'params' => [],
                'response' => [
                    'ok' => true,
                    'data' => [
                        'status' => 'operational',
                        'updated_at' => '2026-09-30T09:36:12+00:00',
                        'services' => [
                            ['name' => 'Main Website', 'slug' => 'main-website', 'status' => 'operational'],
                            ['name' => 'Public API', 'slug' => 'public-api', 'status' => 'degraded'],
                        ],
                    ],
                ],
                'errors' => [['403', 'The public API is disabled in Settings → Public page.']],
            ],
            [
                'id' => 'services',
                'title' => 'List services',
                'method' => 'GET',
                'path' => '/api/status/services',
                'url' => $base.'/services',
                'description' => 'Every service marked public, ordered by sort order then name. Private services never appear.',
                'params' => [],
                'response' => [
                    'ok' => true,
                    'data' => [
                        ['name' => 'Main Website', 'slug' => 'main-website', 'status' => 'operational', 'last_checked_at' => '2026-09-30T09:35:11+00:00'],
                        ['name' => 'Public API', 'slug' => 'public-api', 'status' => 'degraded', 'last_checked_at' => '2026-09-30T09:35:40+00:00'],
                    ],
                ],
                'errors' => [['403', 'The public API is disabled.']],
            ],
            [
                'id' => 'service',
                'title' => 'Get one service',
                'method' => 'GET',
                'path' => '/api/status/services/{slug}',
                'url' => $base.'/services/'.$slug,
                'description' => 'One public service with its 30-day uptime (successful checks ÷ valid checks; maintenance excluded).',
                'params' => [['slug', 'path', 'string', "Service slug, e.g. {$slug}. Private or unknown slugs return 404."]],
                'response' => [
                    'ok' => true,
                    'data' => [
                        'name' => 'Main Website',
                        'slug' => 'main-website',
                        'status' => 'operational',
                        'last_checked_at' => '2026-09-30T09:35:11+00:00',
                        'uptime_30d' => ['uptime' => 99.95, 'total' => 8640, 'successful' => 8636],
                    ],
                ],
                'errors' => [['404', '{"ok":false,"message":"Service not found."}'], ['403', 'The public API is disabled.']],
            ],
            [
                'id' => 'incidents',
                'title' => 'Latest incidents',
                'method' => 'GET',
                'path' => '/api/status/incidents',
                'url' => $base.'/incidents',
                'description' => 'The 25 most recent incidents, newest first. Incidents tied to private services are never returned.',
                'params' => [],
                'response' => [
                    'ok' => true,
                    'data' => [
                        [
                            'title' => 'Public API is down',
                            'slug' => 'public-api-20260930-0904-20',
                            'status' => 'resolved',
                            'impact' => 'major',
                            'service' => 'Public API',
                            'started_at' => '2026-09-30T09:04:20+00:00',
                            'resolved_at' => '2026-09-30T09:05:24+00:00',
                        ],
                    ],
                ],
                'errors' => [['403', 'The public API is disabled.']],
            ],
        ];
    }
}
