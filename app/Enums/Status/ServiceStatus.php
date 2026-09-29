<?php

namespace App\Enums\Status;

enum ServiceStatus: string
{
    case Operational = 'operational';
    case Degraded = 'degraded';
    case PartialOutage = 'partial_outage';
    case MajorOutage = 'major_outage';
    case Maintenance = 'maintenance';
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::Operational => 'Operational',
            self::Degraded => 'Degraded Performance',
            self::PartialOutage => 'Partial Outage',
            self::MajorOutage => 'Major Outage',
            self::Maintenance => 'Scheduled Maintenance',
            self::Unknown => 'Unknown',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Operational => 'green',
            self::Degraded => 'yellow',
            self::PartialOutage => 'orange',
            self::MajorOutage => 'red',
            self::Maintenance => 'blue',
            self::Unknown => 'secondary',
        };
    }

    public function badgeClass(): string
    {
        return 'bg-'.$this->color().'-lt';
    }

    public function isOutage(): bool
    {
        return in_array($this, [self::PartialOutage, self::MajorOutage], true);
    }
}
