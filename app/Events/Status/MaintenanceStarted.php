<?php

namespace App\Events\Status;

use App\Models\Status\StatusMaintenance;
use Illuminate\Foundation\Events\Dispatchable;

class MaintenanceStarted
{
    use Dispatchable;

    public function __construct(
        public StatusMaintenance $maintenance,
    ) {
        //
    }
}
