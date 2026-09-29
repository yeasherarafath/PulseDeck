<?php

namespace App\Http\Controllers\Status;

use App\Enums\Status\ServiceStatus;
use App\Http\Controllers\Controller;
use App\Models\Status\StatusIncident;
use App\Models\Status\StatusService;
use App\Models\Status\StatusSetting;
use App\Services\Status\PublicStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class StatusPageController extends Controller
{
    public function __construct(private PublicStatusService $public)
    {
        //
    }

    public function index(): View
    {
        $this->ensurePublicEnabled();

        $payload = $this->public->payload();

        return view('status.index', $payload);
    }

    public function showService(StatusService $service): View
    {
        $this->ensurePublicEnabled();

        abort_unless($service->is_public, 404);

        return view('status.service', $this->public->servicePayload($service));
    }

    public function showIncident(StatusIncident $incident): View
    {
        $this->ensurePublicEnabled();

        return view('status.incident', [
            'incident' => $incident->load(['service', 'updates']),
        ]);
    }

    /**
     * Lightweight polling payload (same cached builder as the page).
     */
    public function refresh(): JsonResponse
    {
        $this->ensurePublicEnabled();

        $payload = $this->public->payload();

        $services = [];

        foreach ($payload['groups'] as $group) {
            foreach ($group['services'] as $service) {
                $services[] = [
                    'slug' => $service['slug'],
                    'status' => $service['status'],
                    'label' => ServiceStatus::tryFrom($service['status'])?->label() ?? $service['status'],
                ];
            }
        }

        return response()->json([
            'status' => $payload['status'],
            'status_label' => $payload['status_label'],
            'updated_at' => $payload['updated_at'],
            'services' => $services,
        ]);
    }

    private function ensurePublicEnabled(): void
    {
        abort_unless((bool) StatusSetting::get('public_page_enabled', true), 404);
    }
}
