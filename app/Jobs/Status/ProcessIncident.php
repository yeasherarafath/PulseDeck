<?php

namespace App\Jobs\Status;

use App\Enums\Status\CheckErrorType;
use App\Enums\Status\CheckResultStatus;
use App\Models\Status\StatusService;
use App\Services\Status\AssertionResult;
use App\Services\Status\CheckOutcome;
use App\Services\Status\IncidentManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Replays the latest stored check through incident detection.
 * Used by manual triggers and backfills — never by the hot check path.
 */
class ProcessIncident implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(
        public int $serviceId,
    ) {
        //
    }

    public function handle(IncidentManager $incidents): void
    {
        $service = StatusService::find($this->serviceId);

        if (! $service) {
            return;
        }

        $latest = $service->checks()->orderByDesc('checked_at')->orderByDesc('id')->first();

        if (! $latest) {
            return;
        }

        $stored = $latest->assertion_result ?? [];

        $incidents->handleResult(
            $service,
            new CheckOutcome(
                httpStatus: $latest->http_status,
                body: '',
                finalUrl: $latest->final_url ?? '',
                redirectCount: $latest->redirect_count ?? 0,
                responseSize: $latest->response_size,
                responseTimeMs: $latest->response_time,
                connectTimeMs: $latest->connect_time,
                errorType: $latest->error_type instanceof CheckErrorType ? $latest->error_type : CheckErrorType::tryFrom((string) $latest->error_type),
                errorMessage: $latest->error_message,
            ),
            new AssertionResult(
                passed: (bool) ($stored['passed'] ?? (bool) $latest->success),
                failures: $stored['failures'] ?? [],
                warnings: $stored['warnings'] ?? [],
            ),
            $latest->status instanceof CheckResultStatus ? $latest->status : CheckResultStatus::tryFrom((string) $latest->status) ?? CheckResultStatus::Unknown,
        );
    }
}
