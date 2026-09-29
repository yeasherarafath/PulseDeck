<?php

namespace App\Enums\Status;

enum AuthType: string
{
    case None = 'none';
    case Bearer = 'bearer';
    case Basic = 'basic';
    case ApiKey = 'api_key';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::None => 'None',
            self::Bearer => 'Bearer Token',
            self::Basic => 'Basic Auth',
            self::ApiKey => 'API Key',
            self::Custom => 'Custom',
        };
    }
}
