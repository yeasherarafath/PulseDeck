<?php

namespace App\Services\Status;

use App\Enums\Status\CheckResultStatus;
use App\Enums\Status\IncidentImpact;
use App\Enums\Status\IncidentStatus;
use App\Events\Status\IncidentCreated;
use App\Events\Status\IncidentResolved;
use App\Models\Status\StatusIncident;
use App\Models\Status\StatusService;
use Illuminate\Support\Str;

/**
 * Threshold-based automatic incidents. Never opens on a single failure;
 * requires N consecutive failures, and resolves after M recoveries.
 */
class IncidentManager
{
    /**
     * @return array{opened: ?StatusIncident, resolved: ?StatusIncident}
     */
    public function handleResult(
        StatusService $service,
        CheckOutcome $outcome,
        AssertionResult $assertions,
        CheckResultStatus $result,
    ): array {
        $opened = null;
        $resolved = null;

        if ($result === CheckResultStatus::Failed) {
            $opened = $this->maybeOpen($service, $outcome);
        } elseif ($result === CheckResultStatus::Operational || $result === CheckResultStatus::Degraded) {
            $resolved = $this->maybeResolve($service);
        }

        return ['opened' => $opened, 'resolved' => $resolved];
    }

    public function activeForService(StatusService $service): ?StatusIncident
    {
        return $service->incidents()->active()->latest('started_at')->first();
    }

    private function maybeOpen(StatusService $service, CheckOutcome $outcome): ?StatusIncident
    {
        if (! $service->auto_create_incidents) {
            return null;
        }

        if ($this->activeForService($service) !== null) {
            return null;
        }

        $threshold = max(1, $service->failure_threshold ?? 3);

        if (! $this->lastChecksMatch($service, $threshold, false)) {
            return null;
        }

        $summary = $outcome->errorMessage
            ?? ($outcome->httpStatus !== null ? "HTTP {$outcome->httpStatus}" : 'Check failed');

        $incident = StatusIncident::create([
            'service_id' => $service->id,
            'title' => $service->name.' is down',
            'slug' => Str::slug($service->slug.'-'.now()->format('Ymd-Hi-s')),
            'status' => IncidentStatus::Investigating,
            'impact' => IncidentImpact::Major,
            'started_at' => now(),
        ]);

        $incident->updates()->create([
            'status' => IncidentStatus::Investigating,
            'message' => "Automatic incident: {$threshold} consecutive failed checks. Last result: {$summary}.",
        ]);

        IncidentCreated::dispatch($incident);

        return $incident;
    }

    private function maybeResolve(StatusService $service): ?StatusIncident
    {
        if (! $service->auto_resolve_incidents) {
            return null;
        }

        $incident = $this->activeForService($service);

        if ($incident === null) {
            return null;
        }

        $threshold = max(1, $service->recovery_threshold ?? 2);

        if (! $this->lastChecksMatch($service, $threshold, true)) {
            return null;
        }

        $incident->resolve();

        $incident->updates()->create([
            'status' => IncidentStatus::Resolved,
            'message' => "Automatic resolution: {$threshold} consecutive successful checks.",
        ]);

        IncidentResolved::dispatch($incident->fresh());

        return $incident;
    }

    private function lastChecksMatch(StatusService $service, int $count, bool $success): bool
    {
        $checks = $service->checks()->orderByDesc('checked_at')->orderByDesc('id')->limit($count)->get();

        return $checks->count() === $count && $checks->every(fn ($check) => (bool) $check->success === $success);
    }
}
