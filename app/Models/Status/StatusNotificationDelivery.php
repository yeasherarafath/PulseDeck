<?php

namespace App\Models\Status;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StatusNotificationDelivery extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'channel_id',
        'event',
        'service_id',
        'recipient_count',
        'status',
        'error',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'recipient_count' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<StatusNotificationChannel, $this> */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(StatusNotificationChannel::class, 'channel_id');
    }

    /** @return BelongsTo<StatusService, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(StatusService::class, 'service_id');
    }
}
