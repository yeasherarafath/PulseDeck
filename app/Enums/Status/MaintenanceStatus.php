<?php

namespace App\Enums\Status;

enum MaintenanceStatus: string
{
    case Scheduled = 'scheduled';
    case Active = 'active';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Scheduled',
            self::Active => 'Active',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Scheduled => 'blue',
            self::Active => 'yellow',
            self::Completed => 'green',
            self::Cancelled => 'secondary',
        };
    }

    public function badgeClass(): string
    {
        return 'bg-'.$this->color().'-lt';
    }
}
