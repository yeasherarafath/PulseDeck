<?php

namespace App\Enums\Status;

enum CheckInterval: int
{
    case OneMinute = 60;
    case FiveMinutes = 300;
    case TenMinutes = 600;
    case FifteenMinutes = 900;
    case ThirtyMinutes = 1800;
    case OneHour = 3600;

    public function label(): string
    {
        return match ($this) {
            self::OneMinute => '1 minute',
            self::FiveMinutes => '5 minutes',
            self::TenMinutes => '10 minutes',
            self::FifteenMinutes => '15 minutes',
            self::ThirtyMinutes => '30 minutes',
            self::OneHour => '1 hour',
        };
    }

    public function seconds(): int
    {
        return $this->value;
    }
}
