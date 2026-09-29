<?php

namespace App\Enums\Status;

enum IncidentImpact: string
{
    case Minor = 'minor';
    case Major = 'major';
    case Critical = 'critical';

    public function label(): string
    {
        return match ($this) {
            self::Minor => 'Minor',
            self::Major => 'Major',
            self::Critical => 'Critical',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Minor => 'yellow',
            self::Major => 'orange',
            self::Critical => 'red',
        };
    }

    public function badgeClass(): string
    {
        return 'bg-'.$this->color().'-lt';
    }
}
