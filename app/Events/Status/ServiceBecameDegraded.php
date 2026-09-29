<?php

namespace App\Events\Status;

use App\Models\Status\StatusService;
use App\Services\Status\CheckOutcome;
use Illuminate\Foundation\Events\Dispatchable;

class ServiceBecameDegraded
{
    use Dispatchable;

    public function __construct(
        public StatusService $service,
        public ?CheckOutcome $outcome = null,
    ) {
        //
    }
}
