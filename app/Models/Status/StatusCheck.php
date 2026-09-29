<?php

namespace App\Models\Status;

use App\Enums\Status\CheckErrorType;
use App\Enums\Status\CheckResultStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StatusCheck extends Model
{
    protected $fillable = [
        'service_id',
        'success',
        'status',
        'http_status',
        'response_time',
        'connect_time',
        'final_url',
        'redirect_count',
        'response_size',
        'error_type',
        'error_message',
        'assertion_result',
        'checked_at',
    ];

    protected function casts(): array
    {
        return [
            'success' => 'boolean',
            'status' => CheckResultStatus::class,
            'http_status' => 'integer',
            'response_time' => 'integer',
            'connect_time' => 'integer',
            'redirect_count' => 'integer',
            'response_size' => 'integer',
            'error_type' => CheckErrorType::class,
            'assertion_result' => 'array',
            'checked_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<StatusService, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(StatusService::class, 'service_id');
    }
}
