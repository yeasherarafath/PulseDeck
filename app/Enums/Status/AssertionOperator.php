<?php

namespace App\Enums\Status;

enum AssertionOperator: string
{
    case Equals = 'equals';
    case NotEquals = 'not_equals';
    case Contains = 'contains';
    case NotContains = 'not_contains';
    case Exists = 'exists';
    case NotExists = 'not_exists';
    case GreaterThan = 'greater_than';
    case LessThan = 'less_than';
    case Matches = 'matches';

    public function label(): string
    {
        return match ($this) {
            self::Equals => 'Equals',
            self::NotEquals => 'Not equals',
            self::Contains => 'Contains',
            self::NotContains => 'Does not contain',
            self::Exists => 'Exists',
            self::NotExists => 'Does not exist',
            self::GreaterThan => 'Greater than',
            self::LessThan => 'Less than',
            self::Matches => 'Regex matches',
        };
    }

    public function needsExpectedValue(): bool
    {
        return ! in_array($this, [self::Exists, self::NotExists], true);
    }
}
