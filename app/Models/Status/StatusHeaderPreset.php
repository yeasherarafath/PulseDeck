<?php

namespace App\Models\Status;

use App\Enums\Status\HeaderPresetCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Suggestable request header (name + value hints), managed from the database
 * so new headers can be added without touching Blade.
 */
class StatusHeaderPreset extends Model
{
    protected $fillable = [
        'name',
        'header_name',
        'description',
        'category',
        'input_type',
        'options',
        'is_sensitive',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'category' => HeaderPresetCategory::class,
            'options' => 'array',
            'is_sensitive' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** @param Builder<$this> $query */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order')->orderBy('name');
    }
}
