<?php

namespace App\Models\Status;

use App\Enums\Status\SettingGroup;
use App\Enums\Status\SettingType;
use App\Services\Status\PublicStatusService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Key-value application settings (final-plan §3.2).
 * Secrets are encrypted when the row is flagged is_encrypted.
 */
class StatusSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'type',
        'group',
        'is_encrypted',
        'description',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => SettingType::class,
            'group' => SettingGroup::class,
            'is_encrypted' => 'boolean',
        ];
    }

    /** @param Builder<$this> $query */
    public function scopeForGroup(Builder $query, SettingGroup|string $group): Builder
    {
        return $query->where('group', $group instanceof SettingGroup ? $group->value : $group)->orderBy('key');
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        // Cache the computed plain value (never the model): cached models
        // can unserialize as __PHP_Incomplete_Class across processes.
        return Cache::remember('status-setting-v1:'.$key, 60, function () use ($key, $default): mixed {
            $row = static::where('key', $key)->first();

            if (! $row) {
                return $default;
            }

            $raw = $row->is_encrypted && $row->value !== null ? decrypt($row->value) : $row->value;

            return match ($row->type) {
                SettingType::Integer => $raw === null ? $default : (int) $raw,
                SettingType::Boolean => $raw === null ? $default : filter_var($raw, FILTER_VALIDATE_BOOLEAN),
                SettingType::Json => $raw === null || $raw === '' ? $default : json_decode($raw, true),
                default => $raw ?? $default,
            };
        });
    }

    public static function set(string $key, mixed $value, ?int $updatedBy = null): void
    {
        $row = static::where('key', $key)->firstOrFail();

        $raw = match ($row->type) {
            SettingType::Boolean => $value ? '1' : '0',
            SettingType::Json => json_encode($value),
            default => $value === null ? null : (string) $value,
        };

        $row->forceFill([
            'value' => $row->is_encrypted && $raw !== null ? encrypt($raw) : $raw,
            'updated_by' => $updatedBy,
        ])->save();

        Cache::forget('status-setting-v1:'.$key);
        PublicStatusService::flush();
    }
}
