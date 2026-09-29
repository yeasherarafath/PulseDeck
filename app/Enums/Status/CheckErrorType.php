<?php

namespace App\Enums\Status;

enum CheckErrorType: string
{
    case Dns = 'dns';
    case Timeout = 'timeout';
    case Connection = 'connection';
    case Tls = 'tls';
    case Http4xx = 'http_4xx';
    case Http5xx = 'http_5xx';
    case Assertion = 'assertion';
    case Redirect = 'redirect';
    case SsrfBlocked = 'ssrf_blocked';
    case Oversized = 'oversized';
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::Dns => 'DNS failure',
            self::Timeout => 'Timeout',
            self::Connection => 'Connection refused',
            self::Tls => 'TLS error',
            self::Http4xx => 'HTTP client error',
            self::Http5xx => 'HTTP server error',
            self::Assertion => 'Assertion failed',
            self::Redirect => 'Redirect error',
            self::SsrfBlocked => 'Blocked (SSRF guard)',
            self::Oversized => 'Response too large',
            self::Unknown => 'Unknown error',
        };
    }
}
