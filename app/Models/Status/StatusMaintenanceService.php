<?php

namespace App\Models\Status;

use Illuminate\Database\Eloquent\Relations\Pivot;

class StatusMaintenanceService extends Pivot
{
    public $incrementing = false;

    public $timestamps = false;

    protected $table = 'status_maintenance_service';
}
