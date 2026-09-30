<?php

namespace App\Enums\Status;

enum ScheduleType: string
{
    case Interval = 'interval';
    case Cron = 'cron';

    public function label(): string
    {
        return match ($this) {
            self::Interval => 'Fixed interval',
            self::Cron => 'Cron expression',
        };
    }
}
