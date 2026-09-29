<?php

namespace App\Services\Status;

use App\Enums\Status\CheckErrorType;
use App\Enums\Status\CheckResultStatus;

/**
 * Single decision point for check results. Order is fixed (final-plan §2):
 * maintenance → transport failure → bad HTTP status → failed assertions →
 * too slow (failure) → slow (degraded) → operational.
 */
class StatusCalculator
{
    public function calculate(
        StatusRequestDefinition $definition,
        CheckOutcome $outcome,
        AssertionResult $assertions,
        bool $maintenanceActive = false,
    ): CheckResultStatus {
        if ($maintenanceActive) {
            return CheckResultStatus::Maintenance;
        }

        if ($outcome->errorType !== null || $outcome->failedTransport()) {
            return CheckResultStatus::Failed;
        }

        if ($definition->expectedStatusCodes !== [] && ! in_array($outcome->httpStatus, $definition->expectedStatusCodes, true)) {
            return CheckResultStatus::Failed;
        }

        if (! $assertions->passed) {
            return CheckResultStatus::Failed;
        }

        if ($outcome->responseTimeMs !== null
            && $definition->responseTimeFailure !== null
            && $outcome->responseTimeMs > $definition->responseTimeFailure) {
            return CheckResultStatus::Failed;
        }

        if ($assertions->warnings !== []) {
            return CheckResultStatus::Degraded;
        }

        return CheckResultStatus::Operational;
    }

    public function errorTypeFor(CheckOutcome $outcome, AssertionResult $assertions): ?CheckErrorType
    {
        if ($outcome->errorType !== null) {
            return $outcome->errorType;
        }

        if ($outcome->httpStatus !== null && $outcome->httpStatus >= 400) {
            return $outcome->httpStatus >= 500 ? CheckErrorType::Http5xx : CheckErrorType::Http4xx;
        }

        if (! $assertions->passed) {
            return CheckErrorType::Assertion;
        }

        return null;
    }
}
