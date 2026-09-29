<?php

namespace App\Models\Status;

use App\Enums\Status\NotificationChannelType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property array<string, mixed>|null $config Encrypted (tokens, secrets).
 */
class StatusNotificationChannel extends Model
{
    protected $fillable = [
        'name',
        'type',
        'config',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => NotificationChannelType::class,
            'config' => 'encrypted:array',
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<StatusNotificationRule, $this> */
    public function rules(): HasMany
    {
        return $this->hasMany(StatusNotificationRule::class, 'channel_id');
    }
}
