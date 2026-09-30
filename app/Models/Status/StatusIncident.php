<?php

namespace App\Models\Status;

use App\Enums\Status\IncidentImpact;
use App\Enums\Status\IncidentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class StatusIncident extends Model
{
    protected $fillable = [
        'service_id',
        'title',
        'slug',
        'status',
        'impact',
        'started_at',
        'resolved_at',
        'created_by',
        'updated_by',
    ];

    protected $attributes = [
        'status' => 'investigating',
        'impact' => 'minor',
    ];

    protected function casts(): array
    {
        return [
            'status' => IncidentStatus::class,
            'impact' => IncidentImpact::class,
            'started_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $incident): void {
            if (blank($incident->slug)) {
                $incident->slug = Str::slug($incident->title.'-'.now()->format('Ymd-Hi'));
            }
        });
    }

    /** @return BelongsTo<StatusService, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(StatusService::class, 'service_id');
    }

    /** @return HasMany<StatusIncidentUpdate, $this> */
    public function updates(): HasMany
    {
        return $this->hasMany(StatusIncidentUpdate::class, 'incident_id')->oldest();
    }

    /** @param Builder<$this> $query */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNot('status', IncidentStatus::Resolved);
    }

    /**
     * Incidents safe to show publicly: multi-service ones, or those tied to a public service.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeVisibleToPublic(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->whereNull('service_id')
            ->orWhereHas('service', fn ($service) => $service->where('is_public', true)));
    }

    public function isVisibleToPublic(): bool
    {
        return $this->service_id === null || (bool) $this->service?->is_public;
    }

    public function resolve(): void
    {
        $this->forceFill([
            'status' => IncidentStatus::Resolved,
            'resolved_at' => now(),
        ])->save();
    }
}
