<?php

namespace App\Enums\Status;

enum BodyAssertionType: string
{
    case Contains = 'contains';
    case NotContains = 'not_contains';
    case Equals = 'equals';
    case Regex = 'regex';

    public function label(): string
    {
        return match ($this) {
            self::Contains => 'Contains',
            self::NotContains => 'Does not contain',
            self::Equals => 'Equals',
            self::Regex => 'Regex match',
        };
    }
}
