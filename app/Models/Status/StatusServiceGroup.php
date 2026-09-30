<?php

namespace App\Models\Status;

use App\Services\Status\PublicStatusService;
use Database\Factories\StatusServiceGroupFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 */
class StatusServiceGroup extends Model
{
    /** @use HasFactory<StatusServiceGroupFactory> */
    use HasFactory;

    protected static function newFactory(): StatusServiceGroupFactory
    {
        return StatusServiceGroupFactory::new();
    }

    protected $fillable = [
        'name',
        'slug',
        'description',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => PublicStatusService::flush());
        static::deleted(fn () => PublicStatusService::flush());

        static::creating(function (self $group): void {
            if (blank($group->slug)) {
                $group->slug = Str::slug($group->name);
            }
        });
    }

    /** @return HasMany<StatusService, $this> */
    public function services(): HasMany
    {
        return $this->hasMany(StatusService::class, 'group_id')->orderBy('sort_order')->orderBy('name');
    }

    /** @param Builder<$this> $query */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** @param Builder<$this> $query */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
