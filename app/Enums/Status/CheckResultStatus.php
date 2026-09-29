<?php

namespace App\Enums\Status;

enum CheckResultStatus: string
{
    case Operational = 'operational';
    case Degraded = 'degraded';
    case Failed = 'failed';
    case Maintenance = 'maintenance';
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::Operational => 'Operational',
            self::Degraded => 'Degraded',
            self::Failed => 'Failed',
            self::Maintenance => 'Maintenance',
            self::Unknown => 'Unknown',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Operational => 'green',
            self::Degraded => 'yellow',
            self::Failed => 'red',
            self::Maintenance => 'blue',
            self::Unknown => 'secondary',
        };
    }

    public function toServiceStatus(): ServiceStatus
    {
        return match ($this) {
            self::Operational => ServiceStatus::Operational,
            self::Degraded => ServiceStatus::Degraded,
            self::Failed => ServiceStatus::MajorOutage,
            self::Maintenance => ServiceStatus::Maintenance,
            self::Unknown => ServiceStatus::Unknown,
        };
    }
}
