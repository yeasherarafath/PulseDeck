<?php

namespace App\Models\Status;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Named header bundle (e.g. "JSON API") applied with one click.
 * Values are encrypted because templates may contain secrets.
 *
 * @property array<string, string>|null $headers
 */
class StatusHeaderTemplate extends Model
{
    protected $fillable = [
        'name',
        'description',
        'headers',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'headers' => 'encrypted:array',
            'is_active' => 'boolean',
        ];
    }

    /** @param Builder<$this> $query */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('name');
    }
}
