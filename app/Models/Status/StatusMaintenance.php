<?php

namespace App\Models\Status;

use App\Enums\Status\MaintenanceStatus;
use App\Services\Status\PublicStatusService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class StatusMaintenance extends Model
{
    protected $fillable = [
        'title',
        'description',
        'starts_at',
        'ends_at',
        'status',
        'created_by',
    ];

    protected $attributes = [
        'status' => 'scheduled',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'status' => MaintenanceStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => PublicStatusService::flush());
        static::deleted(fn () => PublicStatusService::flush());
    }

    /** @return BelongsToMany<StatusService> */
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(StatusService::class, 'status_maintenance_service', 'maintenance_id', 'service_id')
            ->using(StatusMaintenanceService::class);
    }

    /**
     * Ask the scheduler to re-check every affected service on its next run,
     * so a window starting, ending or being cancelled shows up promptly
     * instead of after the full check interval.
     */
    public function requeueServices(): void
    {
        StatusService::query()
            ->whereIn('id', $this->services()->pluck('status_services.id'))
            ->update(['next_check_at' => now()]);
    }

    /** @param Builder<$this> $query */
    public function scopeCurrentlyActive(Builder $query): Builder
    {
        return $query->where('status', MaintenanceStatus::Active)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now());
    }

    public function coversService(int $serviceId): bool
    {
        return $this->services()->where('status_services.id', $serviceId)->exists();
    }
}
