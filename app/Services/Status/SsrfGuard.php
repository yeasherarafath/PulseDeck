<?php

namespace App\Services\Status;

/**
 * Mandatory SSRF protection for every outgoing monitoring request.
 *
 * Validates the original URL AND every redirect target, after DNS
 * resolution, so private/loopback/link-local/metadata addresses can never
 * be reached — even via DNS rebinding.
 */
class SsrfGuard
{
    /** @var list<string> */
    private const ALLOWED_SCHEMES = ['http', 'https'];

    /**
     * @throws SsrfBlockedException
     */
    public function assertSafeUrl(string $url): void
    {
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['host']) || $parts['host'] === '') {
            throw new SsrfBlockedException('Malformed URL or missing host.');
        }

        $scheme = strtolower($parts['scheme'] ?? '');

        if (! in_array($scheme, self::ALLOWED_SCHEMES, true)) {
            throw new SsrfBlockedException("URL scheme '{$scheme}' is not allowed. Only http and https may be monitored.");
        }

        $this->assertSafeHost($parts['host']);
    }

    /**
     * @throws SsrfBlockedException
     */
    public function assertSafeHost(string $host): void
    {
        $host = trim($host, "[] \t\n\r\0\x0B");

        if ($host === '' || str_contains($host, ' ') || str_contains($host, '/')) {
            throw new SsrfBlockedException('Invalid host name.');
        }

        $normalized = strtolower(rtrim($host, '.'));

        if ($normalized === 'localhost' || str_ends_with($normalized, '.localhost')) {
            throw new SsrfBlockedException('Local host names may not be monitored.');
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $this->assertPublicIp($host);

            return;
        }

        // Resolve and validate EVERY address (mitigates naive DNS rebinding).
        // If DNS fails here, the HTTP layer reports the precise DNS error.
        foreach ($this->resolveHost($host) as $ip) {
            $this->assertPublicIp($ip);
        }
    }

    /**
     * @return list<string>
     */
    private function resolveHost(string $host): array
    {
        $ips = [];

        foreach (@dns_get_record($host, DNS_A) ?: [] as $record) {
            if (isset($record['ip'])) {
                $ips[] = $record['ip'];
            }
        }

        foreach (@dns_get_record($host, DNS_AAAA) ?: [] as $record) {
            if (isset($record['ipv6'])) {
                $ips[] = $record['ipv6'];
            }
        }

        if ($ips === [] && ($resolved = @gethostbyname($host)) !== $host) {
            $ips[] = $resolved;
        }

        return array_values(array_unique($ips));
    }

    /**
     * @throws SsrfBlockedException
     */
    private function assertPublicIp(string $ip): void
    {
        $public = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);

        if ($public === false) {
            throw new SsrfBlockedException("Target {$ip} is not a public address and may not be monitored.");
        }
    }
}
