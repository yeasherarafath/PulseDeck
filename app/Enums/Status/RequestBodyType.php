<?php

namespace App\Enums\Status;

enum RequestBodyType: string
{
    case None = 'none';
    case Json = 'json';
    case Form = 'form';
    case Urlencoded = 'urlencoded';
    case Raw = 'raw';

    public function label(): string
    {
        return match ($this) {
            self::None => 'None',
            self::Json => 'JSON',
            self::Form => 'Form Data',
            self::Urlencoded => 'x-www-form-urlencoded',
            self::Raw => 'Raw',
        };
    }

    public function contentType(): ?string
    {
        return match ($this) {
            self::Json => 'application/json',
            self::Form => 'multipart/form-data',
            self::Urlencoded => 'application/x-www-form-urlencoded',
            default => null,
        };
    }
}
