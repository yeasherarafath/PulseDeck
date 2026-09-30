<?php

namespace Tests\Unit\Status;

use App\Services\Status\SsrfBlockedException;
use App\Services\Status\SsrfGuard;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SsrfGuardTest extends TestCase
{
    /** @return array<string, array{string}> */
    public static function blockedUrls(): array
    {
        return [
            'localhost' => ['http://localhost/'],
            'sub.localhost' => ['http://api.localhost/'],
            'loopback' => ['http://127.0.0.1/'],
            'private 10' => ['http://10.0.0.5/'],
            'private 172' => ['http://172.16.0.1/'],
            'private 192' => ['http://192.168.1.1/'],
            'ipv6 loopback' => ['http://[::1]/'],
            'metadata' => ['http://169.254.169.254/latest/meta-data'],
            'file scheme' => ['file:///etc/passwd'],
            'ftp scheme' => ['ftp://example.com/'],
            'gopher scheme' => ['gopher://example.com/'],
            'no host' => ['http:///path'],
        ];
    }

    #[DataProvider('blockedUrls')]
    public function test_unsafe_urls_are_blocked(string $url): void
    {
        $this->expectException(SsrfBlockedException::class);

        (new SsrfGuard)->assertSafeUrl($url);
    }

    public function test_public_literal_ip_is_allowed_and_not_pinned(): void
    {
        $this->assertSame([], (new SsrfGuard)->assertSafeUrl('https://93.184.216.34/'));
    }

    public function test_pin_options_build_curl_resolve_entry(): void
    {
        if (! defined('CURLOPT_RESOLVE')) {
            $this->markTestSkipped('cURL not available.');
        }

        $options = SsrfGuard::pinOptions('https://example.com/x', ['93.184.216.34', '2606:2800:220:1::1']);

        $this->assertSame(
            ['example.com:443:93.184.216.34,[2606:2800:220:1::1]'],
            $options['curl'][CURLOPT_RESOLVE],
        );
        $this->assertSame([], SsrfGuard::pinOptions('https://example.com/', []));
    }
}
