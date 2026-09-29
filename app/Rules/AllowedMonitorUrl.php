<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Structural URL safety for admin input (no DNS lookups here):
 * http/https only, resolvable-looking host, literal IPs must be public.
 * Full SSRF validation (incl. DNS + redirects) runs in SsrfGuard at check time.
 */
class AllowedMonitorUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || trim($value) === '') {
            return;
        }

        $parts = parse_url($value);

        if ($parts === false || ! isset($parts['host']) || $parts['host'] === '') {
            $fail('The :attribute must be a valid absolute URL with a host.');

            return;
        }

        $scheme = strtolower($parts['scheme'] ?? '');

        if (! in_array($scheme, ['http', 'https'], true)) {
            $fail('The :attribute must use http or https.');

            return;
        }

        $host = trim($parts['host'], '[]');

        if (str_contains($host, ' ') || str_contains($host, '/')) {
            $fail('The :attribute host is invalid.');

            return;
        }

        $normalized = strtolower(rtrim($host, '.'));

        if ($normalized === 'localhost' || str_ends_with($normalized, '.localhost')) {
            $fail('The :attribute must not target localhost.');

            return;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)
            && filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            $fail('The :attribute must not target a private or reserved IP address.');
        }
    }
}
