<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Status\StatusIncident;
use App\Models\Status\StatusService;
use App\Models\Status\StatusSetting;
use App\Services\Status\PublicStatusService;
use App\Services\Status\UptimeCalculator;
use Illuminate\Http\JsonResponse;

class StatusApiController extends Controller
{
    public function __construct(private PublicStatusService $public)
    {
        //
    }

    public function index(): JsonResponse
    {
        $this->ensureEnabled();

        $payload = $this->public->payload();

        $services = [];

        foreach ($payload['groups'] as $group) {
            foreach ($group['services'] as $service) {
                $services[] = [
                    'name' => $service['name'],
                    'slug' => $service['slug'],
                    'status' => $service['status'],
                ];
            }
        }

        return $this->ok([
            'status' => $payload['status'],
            'updated_at' => $payload['updated_at'],
            'services' => $services,
        ]);
    }

    public function services(): JsonResponse
    {
        $this->ensureEnabled();

        return $this->ok(
            StatusService::public()->orderBy('sort_order')->orderBy('name')
                ->get(['name', 'slug', 'current_status', 'last_checked_at'])
                ->map(fn ($service) => [
                    'name' => $service->name,
                    'slug' => $service->slug,
                    'status' => $service->current_status->value,
                    'last_checked_at' => $service->last_checked_at?->toIso8601String(),
                ])->all()
        );
    }

    public function show(string $slug): JsonResponse
    {
        $this->ensureEnabled();

        $service = StatusService::where('slug', $slug)->where('is_public', true)->first();

        if (! $service) {
            return $this->fail('Service not found.', 404);
        }

        return $this->ok([
            'name' => $service->name,
            'slug' => $service->slug,
            'status' => $service->current_status->value,
            'last_checked_at' => $service->last_checked_at?->toIso8601String(),
            'uptime_30d' => app(UptimeCalculator::class)->forService($service, 30),
        ]);
    }

    public function incidents(): JsonResponse
    {
        $this->ensureEnabled();

        return $this->ok(
            StatusIncident::with('service')->latest('started_at')->limit(25)
                ->get()
                ->map(fn ($incident) => [
                    'title' => $incident->title,
                    'slug' => $incident->slug,
                    'status' => $incident->status->value,
                    'impact' => $incident->impact->value,
                    'service' => $incident->service?->name,
                    'started_at' => $incident->started_at->toIso8601String(),
                    'resolved_at' => $incident->resolved_at?->toIso8601String(),
                ])->all()
        );
    }

    private function ensureEnabled(): void
    {
        abort_unless((bool) StatusSetting::get('public_api_enabled', true), 403, 'Public API is disabled.');
    }

    private function ok(mixed $data): JsonResponse
    {
        return response()->json(['ok' => true, 'data' => $data]);
    }

    private function fail(string $message, int $status = 400): JsonResponse
    {
        return response()->json(['ok' => false, 'message' => $message], $status);
    }
}
