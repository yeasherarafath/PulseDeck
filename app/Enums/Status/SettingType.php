<?php

namespace App\Enums\Status;

enum SettingType: string
{
    case String = 'string';
    case Integer = 'integer';
    case Boolean = 'boolean';
    case Json = 'json';

    public function label(): string
    {
        return match ($this) {
            self::String => 'Text',
            self::Integer => 'Number',
            self::Boolean => 'On / Off',
            self::Json => 'JSON',
        };
    }
}
