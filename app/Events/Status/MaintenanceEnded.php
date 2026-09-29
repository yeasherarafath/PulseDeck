<?php

namespace App\Events\Status;

use App\Models\Status\StatusMaintenance;
use Illuminate\Foundation\Events\Dispatchable;

class MaintenanceEnded
{
    use Dispatchable;

    public function __construct(
        public StatusMaintenance $maintenance,
    ) {
        //
    }
}
