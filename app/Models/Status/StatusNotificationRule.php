<?php

namespace App\Models\Status;

use App\Enums\Status\NotificationEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StatusNotificationRule extends Model
{
    protected $fillable = [
        'channel_id',
        'service_id',
        'event',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'event' => NotificationEvent::class,
            'is_active' => 'boolean',
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
