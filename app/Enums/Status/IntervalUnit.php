<?php

namespace App\Enums\Status;

enum IntervalUnit: string
{
    case Seconds = 'seconds';
    case Minutes = 'minutes';
    case Hours = 'hours';
    case Days = 'days';
    case Weeks = 'weeks';
    case Months = 'months';
    case Years = 'years';

    public function label(): string
    {
        return match ($this) {
            self::Seconds => 'Second(s)',
            self::Minutes => 'Minute(s)',
            self::Hours => 'Hour(s)',
            self::Days => 'Day(s)',
            self::Weeks => 'Week(s)',
            self::Months => 'Month(s)',
            self::Years => 'Year(s)',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Seconds => 'sec',
            self::Minutes => 'min',
            self::Hours => 'hr',
            self::Days => 'day',
            self::Weeks => 'wk',
            self::Months => 'mo',
            self::Years => 'yr',
        };
    }

    /**
     * Fixed second equivalents. Months/years are calendar-approximate
     * (30 / 365 days) so schedules stay predictable without TZ math.
     */
    public function seconds(): int
    {
        return match ($this) {
            self::Seconds => 1,
            self::Minutes => 60,
            self::Hours => 3600,
            self::Days => 86400,
            self::Weeks => 604800,
            self::Months => 2592000,
            self::Years => 31536000,
        };
    }

    /**
     * Sensible per-unit bounds so the UI can clamp early
     * (the global 60s–1yr window is still enforced server-side).
     *
     * @return array{min: int, max: int}
     */
    public function bounds(): array
    {
        return match ($this) {
            self::Seconds => ['min' => 60, 'max' => 86400],
            self::Minutes => ['min' => 1, 'max' => 1440],
            self::Hours => ['min' => 1, 'max' => 720],
            self::Days => ['min' => 1, 'max' => 365],
            self::Weeks => ['min' => 1, 'max' => 52],
            self::Months => ['min' => 1, 'max' => 12],
            self::Years => ['min' => 1, 'max' => 1],
        };
    }
}
