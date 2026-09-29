<?php

namespace App\Enums\Status;

enum HttpVersion: string
{
    case Auto = 'auto';
    case Http10 = '1.0';
    case Http11 = '1.1';
    case Http20 = '2.0';

    public function label(): string
    {
        return match ($this) {
            self::Auto => 'Auto',
            self::Http10 => 'HTTP/1.0',
            self::Http11 => 'HTTP/1.1',
            self::Http20 => 'HTTP/2',
        };
    }
}
