<?php

namespace App\Events\Status;

use App\Enums\Status\CheckResultStatus;
use App\Models\Status\StatusService;
use App\Services\Status\AssertionResult;
use App\Services\Status\CheckOutcome;
use Illuminate\Foundation\Events\Dispatchable;

class ServiceCheckCompleted
{
    use Dispatchable;

    public function __construct(
        public StatusService $service,
        public CheckOutcome $outcome,
        public AssertionResult $assertions,
        public CheckResultStatus $result,
    ) {
        //
    }
}
