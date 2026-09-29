<?php

namespace App\Events\Status;

use App\Models\Status\StatusIncident;
use Illuminate\Foundation\Events\Dispatchable;

class IncidentCreated
{
    use Dispatchable;

    public function __construct(
        public StatusIncident $incident,
    ) {
        //
    }
}
