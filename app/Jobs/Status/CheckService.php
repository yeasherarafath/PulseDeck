<?php

namespace App\Jobs\Status;

use App\Enums\Status\CheckErrorType;
use App\Enums\Status\CheckResultStatus;
use App\Enums\Status\ServiceStatus;
use App\Events\Status\ServiceBecameDegraded;
use App\Events\Status\ServiceCheckCompleted;
use App\Events\Status\ServiceRecovered;
use App\Events\Status\ServiceWentDown;
use App\Models\Status\StatusService;
use App\Models\Status\StatusSetting;
use App\Services\Status\AssertionEngine;
use App\Services\Status\AssertionResult;
use App\Services\Status\CheckOutcome;
use App\Services\Status\HttpChecker;
use App\Services\Status\IncidentManager;
use App\Services\Status\MaintenanceManager;
use App\Services\Status\PublicStatusService;
use App\Services\Status\RequestBuilder;
use App\Services\Status\StatusCalculator;
use App\Services\Status\StatusRequestDefinition;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Single checking pipeline for scheduled runs, Check Now, and retries.
 * Controllers never check directly — they dispatch this job.
 */
class CheckService implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    /** @var list<int> */
    public array $backoff = [10, 30];

    public function __construct(
        public int $serviceId,
        public bool $manual = false,
    ) {
        //
    }

    public function handle(
        RequestBuilder $builder,
        HttpChecker $checker,
        AssertionEngine $assertions,
        StatusCalculator $calculator,
        IncidentManager $incidents,
        MaintenanceManager $maintenance,
    ): void {
        $service = StatusService::find($this->serviceId);

        if (! $service) {
            return;
        }

        if (! $service->is_active && ! $this->manual) {
            $service->forceFill(['next_check_at' => $service->calculateNextCheckAt()])->save();

            return;
        }

        $lock = Cache::lock("status-check:{$service->id}", 120);

        if (! $lock->get()) {
            return;
        }

        try {
            $this->run($service, $builder, $checker, $assertions, $calculator, $incidents, $maintenance);
        } finally {
            $lock->release();
        }
    }

    private function run(
        StatusService $service,
        RequestBuilder $builder,
        HttpChecker $checker,
        AssertionEngine $assertions,
        StatusCalculator $calculator,
        IncidentManager $incidents,
        MaintenanceManager $maintenance,
    ): void {
        $previous = $service->current_status ?? ServiceStatus::Unknown;
        $maintenanceActive = $maintenance->isUnderMaintenance($service);

        try {
            $definition = $builder->fromService($service);
            $builder->validateDefinition($definition);
            $outcome = $checker->check($definition);
            $assertionResult = $assertions->run($definition, $outcome);
        } catch (Throwable $exception) {
            // Invalid stored configuration: visible failed check, not a crash.
            $definition = new StatusRequestDefinition(method: $service->method, url: $service->url);
            $outcome = CheckOutcome::transportError(CheckErrorType::Unknown, $exception->getMessage(), $service->url);
            $assertionResult = AssertionResult::passed();
        }

        $result = $calculator->calculate($definition, $outcome, $assertionResult, $maintenanceActive);
        $errorType = $calculator->errorTypeFor($outcome, $assertionResult);
        $success = $result === CheckResultStatus::Operational || $result === CheckResultStatus::Degraded;

        $service->checks()->create([
            'success' => $success,
            'status' => $result,
            'http_status' => $outcome->httpStatus,
            'response_time' => $outcome->responseTimeMs,
            'connect_time' => $outcome->connectTimeMs,
            'final_url' => $outcome->finalUrl !== '' ? mb_substr($outcome->finalUrl, 0, 2048) : null,
            'redirect_count' => $outcome->redirectCount,
            'response_size' => $outcome->responseSize,
            'error_type' => $errorType,
            'error_message' => $outcome->errorMessage ?? ($errorType ? $errorType->label() : null),
            'assertion_result' => $assertionResult->toArray(),
            'checked_at' => now(),
        ]);

        $service->recordCheckResult($result, $this->effectiveStatus($service, $result, $previous));

        $incidentOutcome = $incidents->handleResult($service->fresh(), $outcome, $assertionResult, $result);

        // One recovery = one mail: when an incident auto-resolves in this
        // same run, its resolution mail replaces the service.recovered mail.
        $this->fireTransitionEvents($service, $previous, $result, $outcome, $incidentOutcome['resolved'] !== null);

        ServiceCheckCompleted::dispatch($service->fresh(), $outcome, $assertionResult, $result);

        if ($service->fresh()->current_status !== $previous) {
            Cache::forget(PublicStatusService::CACHE_KEY);
            Cache::forget(PublicStatusService::serviceKey($service->id));
        }

        Cache::put('status:monitor:heartbeat', now()->timestamp, 600);
    }

    /**
     * A service is only shown as down after enough consecutive failures:
     * the service's own min_failed_checks_down wins, falling back to the
     * global setting (default 1 = immediate). The failed check row is
     * always recorded; only the public status flip is gated.
     */
    private function effectiveStatus(StatusService $service, CheckResultStatus $result, ServiceStatus $previous): ServiceStatus
    {
        $status = $result->toServiceStatus();

        if ($result !== CheckResultStatus::Failed) {
            return $status;
        }

        $minimum = max(1, $service->min_failed_checks_down ?? (int) StatusSetting::get('min_failed_checks_down', 1));

        if ($minimum <= 1) {
            return $status;
        }

        $consecutive = $service->checks()
            ->orderByDesc('checked_at')
            ->orderByDesc('id')
            ->limit($minimum)
            ->pluck('success');

        if ($consecutive->count() === $minimum && $consecutive->every(fn ($success) => ! (bool) $success)) {
            return $status;
        }

        return $previous;
    }

    private function fireTransitionEvents(
        StatusService $service,
        ServiceStatus $previous,
        CheckResultStatus $result,
        CheckOutcome $outcome,
        bool $incidentResolved = false,
    ): void {
        $fresh = $service->fresh() ?? $service;

        if ($result === CheckResultStatus::Failed && ! $previous->isOutage() && $previous !== ServiceStatus::MajorOutage) {
            ServiceWentDown::dispatch($fresh, $outcome);

            return;
        }

        if ($result === CheckResultStatus::Degraded && $previous === ServiceStatus::Operational) {
            ServiceBecameDegraded::dispatch($fresh, $outcome);

            return;
        }

        if ($incidentResolved) {
            return;
        }

        if ($result === CheckResultStatus::Operational && in_array($previous, [ServiceStatus::Degraded, ServiceStatus::PartialOutage, ServiceStatus::MajorOutage], true)) {
            ServiceRecovered::dispatch($fresh);
        }
    }
}
