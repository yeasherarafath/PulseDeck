<?php

namespace App\Models\Status;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StatusDailyStat extends Model
{
    protected $fillable = [
        'service_id',
        'date',
        'total_checks',
        'successful_checks',
        'failed_checks',
        'uptime_percentage',
        'avg_response_time',
        'min_response_time',
        'max_response_time',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'total_checks' => 'integer',
            'successful_checks' => 'integer',
            'failed_checks' => 'integer',
            'uptime_percentage' => 'decimal:2',
            'avg_response_time' => 'integer',
            'min_response_time' => 'integer',
            'max_response_time' => 'integer',
        ];
    }

    /** @return BelongsTo<StatusService, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(StatusService::class, 'service_id');
    }
}
