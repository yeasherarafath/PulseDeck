<?php

namespace App\Models\Status;

use App\Enums\Status\CheckResultStatus;
use App\Enums\Status\HttpMethod;
use App\Enums\Status\HttpVersion;
use App\Enums\Status\RequestBodyType;
use App\Enums\Status\ServiceStatus;
use Database\Factories\StatusServiceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class StatusService extends Model
{
    /** @use HasFactory<StatusServiceFactory> */
    use HasFactory;

    protected static function newFactory(): StatusServiceFactory
    {
        return StatusServiceFactory::new();
    }

    protected $fillable = [
        'group_id',
        'name',
        'slug',
        'description',
        'url',
        'method',
        'check_interval',
        'timeout',
        'connect_timeout',
        'follow_redirects',
        'max_redirects',
        'verify_ssl',
        'http_version',
        'user_agent',
        'request_headers',
        'query_params',
        'request_body',
        'request_body_type',
        'authentication',
        'expected_status_codes',
        'response_time_warning',
        'response_time_failure',
        'response_assertions',
        'json_assertions',
        'header_assertions',
        'current_status',
        'last_checked_at',
        'last_success_at',
        'last_failure_at',
        'next_check_at',
        'failure_threshold',
        'recovery_threshold',
        'auto_create_incidents',
        'auto_resolve_incidents',
        'notify_on_failure',
        'notify_on_recovery',
        'is_active',
        'is_public',
        'sort_order',
    ];

    protected $attributes = [
        'method' => 'GET',
        'check_interval' => 300,
        'timeout' => 15,
        'connect_timeout' => 5,
        'follow_redirects' => true,
        'max_redirects' => 5,
        'verify_ssl' => true,
        'http_version' => 'auto',
        'request_body_type' => 'none',
        'current_status' => 'unknown',
        'failure_threshold' => 3,
        'recovery_threshold' => 2,
        'auto_create_incidents' => true,
        'auto_resolve_incidents' => true,
        'notify_on_failure' => true,
        'notify_on_recovery' => true,
        'is_active' => true,
        'is_public' => true,
        'sort_order' => 0,
    ];

    protected function casts(): array
    {
        return [
            'method' => HttpMethod::class,
            'check_interval' => 'integer',
            'timeout' => 'integer',
            'connect_timeout' => 'integer',
            'follow_redirects' => 'boolean',
            'max_redirects' => 'integer',
            'verify_ssl' => 'boolean',
            'http_version' => HttpVersion::class,
            'request_headers' => 'encrypted:array',
            'query_params' => 'array',
            'request_body_type' => RequestBodyType::class,
            'authentication' => 'encrypted:array',
            'expected_status_codes' => 'array',
            'response_time_warning' => 'integer',
            'response_time_failure' => 'integer',
            'response_assertions' => 'array',
            'json_assertions' => 'array',
            'header_assertions' => 'array',
            'current_status' => ServiceStatus::class,
            'last_checked_at' => 'datetime',
            'last_success_at' => 'datetime',
            'last_failure_at' => 'datetime',
            'next_check_at' => 'datetime',
            'failure_threshold' => 'integer',
            'recovery_threshold' => 'integer',
            'auto_create_incidents' => 'boolean',
            'auto_resolve_incidents' => 'boolean',
            'notify_on_failure' => 'boolean',
            'notify_on_recovery' => 'boolean',
            'is_active' => 'boolean',
            'is_public' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $service): void {
            if (blank($service->slug)) {
                $service->slug = Str::slug($service->name);
            }
            if ($service->next_check_at === null) {
                $service->next_check_at = now();
            }
        });
    }

    /** @return BelongsTo<StatusServiceGroup, $this> */
    public function group(): BelongsTo
    {
        return $this->belongsTo(StatusServiceGroup::class, 'group_id');
    }

    /** @return HasMany<StatusCheck, $this> */
    public function checks(): HasMany
    {
        return $this->hasMany(StatusCheck::class, 'service_id')->latest('checked_at');
    }

    /** @return HasMany<StatusDailyStat, $this> */
    public function dailyStats(): HasMany
    {
        return $this->hasMany(StatusDailyStat::class, 'service_id');
    }

    /** @return HasMany<StatusIncident, $this> */
    public function incidents(): HasMany
    {
        return $this->hasMany(StatusIncident::class, 'service_id')->latest('started_at');
    }

    /** @return BelongsToMany<StatusMaintenance> */
    public function maintenances(): BelongsToMany
    {
        return $this->belongsToMany(StatusMaintenance::class, 'status_maintenance_service', 'service_id', 'maintenance_id')
            ->using(StatusMaintenanceService::class);
    }

    /** @param Builder<$this> $query */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** @param Builder<$this> $query */
    public function scopePublic(Builder $query): Builder
    {
        return $query->where('is_public', true);
    }

    /** @param Builder<$this> $query */
    public function scopeDue(Builder $query): Builder
    {
        return $query->active()->where('next_check_at', '<=', now());
    }

    public function recordCheckResult(CheckResultStatus $result, ServiceStatus $status): void
    {
        $this->forceFill([
            'current_status' => $status,
            'last_checked_at' => now(),
            'next_check_at' => now()->addSeconds($this->check_interval),
            'last_success_at' => $result === CheckResultStatus::Operational || $result === CheckResultStatus::Degraded
                ? now()
                : $this->last_success_at,
            'last_failure_at' => $result === CheckResultStatus::Failed ? now() : $this->last_failure_at,
        ])->save();
    }
}
