<?php

namespace App\Services\Status;

use App\Enums\Status\AuthType;
use App\Enums\Status\HttpMethod;
use App\Enums\Status\HttpVersion;
use App\Enums\Status\RequestBodyType;
use App\Models\Status\StatusService;
use InvalidArgumentException;

/**
 * Converts stored service configuration into a normalized
 * StatusRequestDefinition. The ONLY interpreter of raw database JSON —
 * jobs, Test Request, and CLI debugging all go through here.
 */
class RequestBuilder
{
    public const DEFAULT_USER_AGENT = 'StatusMonitor/1.0';

    public function __construct(
        private SsrfGuard $guard,
        private HeaderPresetManager $presets,
    ) {
        //
    }

    public function fromService(StatusService $service): StatusRequestDefinition
    {
        $headers = $this->normalizeHeaders($service->request_headers ?? []);
        $query = $this->normalizeMap($service->query_params ?? []);
        $auth = $service->authentication ?? ['type' => AuthType::None->value];

        $basicAuth = null;
        $authType = AuthType::tryFrom($auth['type'] ?? 'none') ?? AuthType::None;

        $headers = match ($authType) {
            AuthType::Bearer => array_merge($headers, ['Authorization' => 'Bearer '.($auth['token'] ?? '')]),
            AuthType::ApiKey => array_merge($headers, [($auth['header'] ?? 'X-API-Key') => ($auth['key'] ?? '')]),
            AuthType::Custom => array_merge($headers, $this->normalizeHeaders($auth['headers'] ?? [])),
            AuthType::Basic => $headers,
            AuthType::None => $headers,
        };

        if ($authType === AuthType::Basic) {
            $basicAuth = [$auth['username'] ?? '', $auth['password'] ?? ''];
        }

        $userAgent = $service->user_agent ?: self::DEFAULT_USER_AGENT;

        if (! $this->hasHeader($headers, 'User-Agent')) {
            $headers['User-Agent'] = $userAgent;
        }

        $bodyType = $service->request_body_type ?? RequestBodyType::None;
        $body = $this->normalizeBody($bodyType, $service->request_body, $headers);

        return new StatusRequestDefinition(
            method: $service->method ?? HttpMethod::Get,
            url: self::buildUrl($service->url, $query),
            headers: $headers,
            query: $query,
            auth: $auth,
            bodyType: $bodyType,
            body: $body,
            basicAuth: $basicAuth,
            timeout: max(1, min(60, $service->timeout ?? 15)),
            connectTimeout: max(1, min(60, $service->connect_timeout ?? 5)),
            followRedirects: (bool) ($service->follow_redirects ?? true),
            maxRedirects: max(0, min(20, $service->max_redirects ?? 5)),
            verifySsl: (bool) ($service->verify_ssl ?? true),
            httpVersion: $service->http_version ?? HttpVersion::Auto,
            userAgent: $userAgent,
            expectedStatusCodes: $this->normalizeStatusCodes($service->expected_status_codes),
            responseTimeWarning: $service->response_time_warning,
            responseTimeFailure: $service->response_time_failure,
            responseAssertions: array_values($service->response_assertions ?? []),
            jsonAssertions: array_values($service->json_assertions ?? []),
            headerAssertions: array_values($service->header_assertions ?? []),
        );
    }

    /**
     * SSRF validation shared by scheduled checks and manual Test Request.
     *
     * @throws SsrfBlockedException
     */
    public function validateDefinition(StatusRequestDefinition $definition): void
    {
        $this->guard->assertSafeUrl($definition->url);
    }

    public static function buildUrl(string $base, array $query): string
    {
        if ($query === []) {
            return $base;
        }

        $separator = str_contains($base, '?') ? '&' : '?';

        return $base.$separator.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * @return array<string, string>
     */
    private function normalizeHeaders(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $headers = [];

        foreach ($raw as $name => $value) {
            // Support both map form {name: value} and row form [{name, value}].
            if (is_array($value) && array_key_exists('name', $value)) {
                $name = $value['name'] ?? '';
                $value = $value['value'] ?? '';
            }

            $name = trim((string) $name);

            if ($name === '' || ! is_scalar($value)) {
                continue;
            }

            $headers[$name] = (string) $value;
        }

        return $headers;
    }

    /**
     * @return array<string, string>
     */
    private function normalizeMap(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $map = [];

        foreach ($raw as $name => $value) {
            if (is_array($value) && array_key_exists('name', $value)) {
                $name = $value['name'] ?? $name;
                $value = $value['value'] ?? '';
            }

            $name = trim((string) $name);

            if ($name === '' || ! is_scalar($value)) {
                continue;
            }

            $map[$name] = (string) $value;
        }

        return $map;
    }

    /**
     * @param  array<string, string>  $headers  Mutated: content-type defaults.
     * @return string|array<string, string>|null
     */
    private function normalizeBody(RequestBodyType $type, ?string $raw, array &$headers): string|array|null
    {
        if ($type === RequestBodyType::None || $raw === null || trim($raw) === '') {
            return null;
        }

        if ($type === RequestBodyType::Raw) {
            if (! $this->hasHeader($headers, 'Content-Type')) {
                $headers['Content-Type'] = 'text/plain';
            }

            return $raw;
        }

        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            throw new InvalidArgumentException('Request body is not valid JSON for type '.$type->value.'.');
        }

        $map = $this->normalizeMap($decoded);

        if ($type === RequestBodyType::Json) {
            if (! $this->hasHeader($headers, 'Content-Type')) {
                $headers['Content-Type'] = 'application/json';
            }

            return $map;
        }

        // Form + urlencoded travel as Guzzle options; drop any admin-set
        // content-type that would break the generated boundary/encoding.
        foreach (array_keys($headers) as $name) {
            if (strtolower($name) === 'content-type') {
                unset($headers[$name]);
            }
        }

        return $map;
    }

    /**
     * @return list<int>
     */
    private function normalizeStatusCodes(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [200];
        }

        $codes = array_values(array_filter(array_map(
            fn (mixed $code) => is_numeric($code) ? (int) $code : null,
            $raw
        )));

        return $codes === [] ? [200] : $codes;
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function hasHeader(array $headers, string $name): bool
    {
        foreach (array_keys($headers) as $existing) {
            if (strtolower($existing) === strtolower($name)) {
                return true;
            }
        }

        return false;
    }
}
