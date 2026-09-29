<?php

namespace App\Enums\Status;

enum HttpMethod: string
{
    case Get = 'GET';
    case Post = 'POST';
    case Put = 'PUT';
    case Patch = 'PATCH';
    case Delete = 'DELETE';
    case Head = 'HEAD';
    case Options = 'OPTIONS';

    public function label(): string
    {
        return $this->value;
    }

    public function allowsBody(): bool
    {
        return in_array($this, [self::Post, self::Put, self::Patch, self::Delete], true);
    }
}
