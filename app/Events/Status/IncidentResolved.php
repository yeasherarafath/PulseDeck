<?php

namespace App\Events\Status;

use App\Models\Status\StatusIncident;
use Illuminate\Foundation\Events\Dispatchable;

class IncidentResolved
{
    use Dispatchable;

    public function __construct(
        public StatusIncident $incident,
    ) {
        //
    }
}
