<?php

namespace App\Enums\Status;

enum HeaderPresetCategory: string
{
    case Common = 'common';
    case Api = 'api';
    case Other = 'other';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::Common => 'Common',
            self::Api => 'API',
            self::Other => 'Other',
            self::Custom => 'Custom',
        };
    }
}
