<?php

namespace App\Events\Status;

use App\Models\Status\StatusService;
use Illuminate\Foundation\Events\Dispatchable;

class ServiceRecovered
{
    use Dispatchable;

    public function __construct(
        public StatusService $service,
    ) {
        //
    }
}
