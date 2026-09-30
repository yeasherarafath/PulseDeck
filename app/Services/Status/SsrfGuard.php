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
     * @return list<string> Validated addresses the host resolved to (empty for literal IPs).
     *
     * @throws SsrfBlockedException
     */
    public function assertSafeUrl(string $url): array
    {
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['host']) || $parts['host'] === '') {
            throw new SsrfBlockedException('Malformed URL or missing host.');
        }

        $scheme = strtolower($parts['scheme'] ?? '');

        if (! in_array($scheme, self::ALLOWED_SCHEMES, true)) {
            throw new SsrfBlockedException("URL scheme '{$scheme}' is not allowed; use http or https.");
        }

        return $this->assertSafeHost($parts['host']);
    }

    /**
     * @return list<string>
     *
     * @throws SsrfBlockedException
     */
    public function assertSafeHost(string $host): array
    {
        $host = trim($host, "[] \t\n\r\0\x0B");

        if ($host === '' || str_contains($host, ' ') || str_contains($host, '/')) {
            throw new SsrfBlockedException('Invalid host name.');
        }

        $normalized = strtolower(rtrim($host, '.'));

        if ($normalized === 'localhost' || str_ends_with($normalized, '.localhost')) {
            throw new SsrfBlockedException('Local host names are not allowed.');
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $this->assertPublicIp($host);

            return [];
        }

        // Resolve and validate EVERY address (mitigates naive DNS rebinding).
        // If DNS fails here, the HTTP layer reports the precise DNS error.
        $ips = $this->resolveHost($host);

        foreach ($ips as $ip) {
            $this->assertPublicIp($ip);
        }

        return $ips;
    }

    /**
     * Guzzle options pinning the connection to already-validated addresses,
     * so DNS cannot change between the safety check and the connect (rebinding).
     *
     * @param  list<string>  $ips
     * @return array<string, mixed>
     */
    public static function pinOptions(string $url, array $ips): array
    {
        if ($ips === [] || ! defined('CURLOPT_RESOLVE')) {
            return [];
        }

        $parts = parse_url($url);
        $port = $parts['port'] ?? (strtolower($parts['scheme'] ?? 'http') === 'https' ? 443 : 80);
        $addresses = array_map(fn (string $ip) => str_contains($ip, ':') ? "[{$ip}]" : $ip, $ips);

        return ['curl' => [CURLOPT_RESOLVE => [$parts['host'].':'.$port.':'.implode(',', $addresses)]]];
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
            throw new SsrfBlockedException("Target {$ip} is not a public address and may not be reached.");
        }
    }
}
