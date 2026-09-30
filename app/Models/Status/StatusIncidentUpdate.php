<?php

namespace App\Models\Status;

use App\Enums\Status\IncidentStatus;
use App\Services\Status\PublicStatusService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StatusIncidentUpdate extends Model
{
    protected $fillable = [
        'incident_id',
        'status',
        'message',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => IncidentStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn (self $update) => PublicStatusService::flush($update->incident?->service_id));
    }

    /** @return BelongsTo<StatusIncident, $this> */
    public function incident(): BelongsTo
    {
        return $this->belongsTo(StatusIncident::class, 'incident_id');
    }
}
