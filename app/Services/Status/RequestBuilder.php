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
        return $this->assemble(
            headers: $this->normalizeHeaders($service->request_headers ?? []),
            query: $this->normalizeMap($service->query_params ?? []),
            auth: $service->authentication ?? ['type' => AuthType::None->value],
            bodyType: $service->request_body_type ?? RequestBodyType::None,
            rawBody: $service->request_body,
            method: $service->method ?? HttpMethod::Get,
            baseUrl: $service->url,
            userAgent: $service->user_agent ?: self::DEFAULT_USER_AGENT,
            timeout: $service->timeout ?? 15,
            connectTimeout: $service->connect_timeout ?? 5,
            followRedirects: (bool) ($service->follow_redirects ?? true),
            maxRedirects: $service->max_redirects ?? 5,
            verifySsl: (bool) ($service->verify_ssl ?? true),
            httpVersion: $service->http_version ?? HttpVersion::Auto,
            expectedStatusCodes: $service->expected_status_codes,
            responseTimeWarning: $service->response_time_warning,
            responseTimeFailure: $service->response_time_failure,
            responseAssertions: $service->response_assertions ?? [],
            jsonAssertions: $service->json_assertions ?? [],
            headerAssertions: $service->header_assertions ?? [],
        );
    }

    /**
     * Build a definition from unsaved admin form input (Test Request before saving).
     *
     * @param  array<string, mixed>  $input  Validated form data (row-form headers/query/body_fields).
     */
    public function fromArray(array $input): StatusRequestDefinition
    {
        $bodyType = RequestBodyType::tryFrom((string) ($input['body_type'] ?? 'none')) ?? RequestBodyType::None;

        $rawBody = $input['body'] ?? null;

        if (in_array($bodyType, [RequestBodyType::Form, RequestBodyType::Urlencoded], true)) {
            $rawBody = json_encode($this->rowsToMap($input['body_fields'] ?? []));
        }

        $statuses = $input['expected_statuses'] ?? [200];

        if (is_string($statuses)) {
            $statuses = explode(',', $statuses);
        }

        return $this->assemble(
            headers: $this->normalizeHeaders($input['headers'] ?? []),
            query: $this->normalizeMap($input['query'] ?? []),
            auth: $this->normalizeAuthInput($input['auth'] ?? []),
            bodyType: $bodyType,
            rawBody: is_string($rawBody) ? $rawBody : null,
            method: HttpMethod::tryFrom(strtoupper((string) ($input['method'] ?? 'GET'))) ?? HttpMethod::Get,
            baseUrl: (string) ($input['url'] ?? ''),
            userAgent: trim((string) ($input['user_agent'] ?? '')) ?: self::DEFAULT_USER_AGENT,
            timeout: (int) ($input['timeout'] ?? 15),
            connectTimeout: (int) ($input['connect_timeout'] ?? 5),
            followRedirects: (bool) ($input['follow_redirects'] ?? true),
            maxRedirects: (int) ($input['max_redirects'] ?? 5),
            verifySsl: (bool) ($input['verify_ssl'] ?? true),
            httpVersion: HttpVersion::tryFrom((string) ($input['http_version'] ?? 'auto')) ?? HttpVersion::Auto,
            expectedStatusCodes: $statuses,
            responseTimeWarning: $this->nullableInt($input['warn_ms'] ?? $input['response_time_warning'] ?? null),
            responseTimeFailure: $this->nullableInt($input['fail_ms'] ?? $input['response_time_failure'] ?? null),
            responseAssertions: $input['body_assertions'] ?? [],
            jsonAssertions: $input['json_assertions'] ?? [],
            headerAssertions: $input['header_assertions'] ?? [],
        );
    }

    /**
     * Map validated admin form input to service attributes for persistence.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function normalizeServiceAttributes(array $input): array
    {
        $bodyType = RequestBodyType::tryFrom((string) ($input['body_type'] ?? 'none')) ?? RequestBodyType::None;

        $rawBody = $input['body'] ?? null;

        if (in_array($bodyType, [RequestBodyType::Form, RequestBodyType::Urlencoded], true)) {
            $rawBody = json_encode($this->rowsToMap($input['body_fields'] ?? []));
        }

        $statuses = $input['expected_statuses'] ?? [200];

        if (is_string($statuses)) {
            $statuses = explode(',', $statuses);
        }

        return [
            'group_id' => $input['group_id'] ?? null,
            'name' => $input['name'],
            'slug' => $input['slug'] ?? null,
            'description' => $input['description'] ?? null,
            'url' => $input['url'],
            'method' => strtoupper((string) ($input['method'] ?? 'GET')),
            'check_interval' => (int) ($input['check_interval'] ?? 300),
            'timeout' => (int) ($input['timeout'] ?? 15),
            'connect_timeout' => (int) ($input['connect_timeout'] ?? 5),
            'follow_redirects' => (bool) ($input['follow_redirects'] ?? false),
            'max_redirects' => (int) ($input['max_redirects'] ?? 5),
            'verify_ssl' => (bool) ($input['verify_ssl'] ?? false),
            'http_version' => (string) ($input['http_version'] ?? 'auto'),
            'user_agent' => $input['user_agent'] ?? null,
            'request_headers' => $this->rowsToMap($input['headers'] ?? []),
            'query_params' => $this->rowsToMap($input['query'] ?? []),
            'request_body' => is_string($rawBody) && trim($rawBody) !== '' ? $rawBody : null,
            'request_body_type' => $bodyType->value,
            'authentication' => $this->normalizeAuthInput($input['auth'] ?? []),
            'expected_status_codes' => $this->normalizeStatusCodes($statuses),
            'response_time_warning' => $this->nullableInt($input['warn_ms'] ?? null),
            'response_time_failure' => $this->nullableInt($input['fail_ms'] ?? null),
            'response_assertions' => array_values($input['body_assertions'] ?? []),
            'json_assertions' => array_values($input['json_assertions'] ?? []),
            'header_assertions' => array_values($input['header_assertions'] ?? []),
            'is_active' => (bool) ($input['is_active'] ?? false),
            'is_public' => (bool) ($input['is_public'] ?? false),
            'sort_order' => (int) ($input['sort_order'] ?? 0),
            'min_failed_checks_down' => $this->nullableInt($input['min_failed_checks_down'] ?? null),
        ];
    }

    /**
     * @param  array<string, string>  $headers
     * @param  array<string, string>  $query
     * @param  array<string, mixed>  $auth
     * @param  list<int>|mixed  $expectedStatusCodes
     * @param  list<array<string, mixed>>  $responseAssertions
     * @param  list<array<string, mixed>>  $jsonAssertions
     * @param  list<array<string, mixed>>  $headerAssertions
     */
    private function assemble(
        array $headers,
        array $query,
        array $auth,
        RequestBodyType $bodyType,
        ?string $rawBody,
        HttpMethod $method,
        string $baseUrl,
        string $userAgent,
        int $timeout,
        int $connectTimeout,
        bool $followRedirects,
        int $maxRedirects,
        bool $verifySsl,
        HttpVersion $httpVersion,
        mixed $expectedStatusCodes,
        mixed $responseTimeWarning,
        mixed $responseTimeFailure,
        array $responseAssertions,
        array $jsonAssertions,
        array $headerAssertions,
    ): StatusRequestDefinition {
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

        if (! $this->hasHeader($headers, 'User-Agent')) {
            $headers['User-Agent'] = $userAgent;
        }

        return new StatusRequestDefinition(
            method: $method,
            url: self::buildUrl($baseUrl, $query),
            headers: $headers,
            query: $query,
            auth: $auth,
            bodyType: $bodyType,
            body: $this->normalizeBody($bodyType, $rawBody, $headers),
            basicAuth: $basicAuth,
            timeout: max(1, min(60, $timeout)),
            connectTimeout: max(1, min(60, $connectTimeout)),
            followRedirects: $followRedirects,
            maxRedirects: max(0, min(20, $maxRedirects)),
            verifySsl: $verifySsl,
            httpVersion: $httpVersion,
            userAgent: $userAgent,
            expectedStatusCodes: $this->normalizeStatusCodes($expectedStatusCodes),
            responseTimeWarning: $this->nullableInt($responseTimeWarning),
            responseTimeFailure: $this->nullableInt($responseTimeFailure),
            responseAssertions: array_values($responseAssertions),
            jsonAssertions: array_values($jsonAssertions),
            headerAssertions: array_values($headerAssertions),
        );
    }

    /**
     * Row-form [{name, value}] or map-form {name: value} → clean map.
     *
     * @return array<string, string>
     */
    private function rowsToMap(mixed $rows): array
    {
        if (! is_array($rows)) {
            return [];
        }

        $map = [];

        foreach ($rows as $name => $row) {
            if (is_array($row) && (array_key_exists('name', $row) || array_key_exists('value', $row))) {
                $name = $row['name'] ?? $name;
                $row = $row['value'] ?? '';
            }

            $name = trim((string) $name);

            if ($name === '' || ! is_scalar($row)) {
                continue;
            }

            $map[$name] = (string) $row;
        }

        return $map;
    }

    /**
     * @param  array<string, mixed>  $auth
     * @return array<string, mixed>
     */
    private function normalizeAuthInput(array $auth): array
    {
        $type = AuthType::tryFrom((string) ($auth['type'] ?? 'none')) ?? AuthType::None;

        $normalized = ['type' => $type->value];

        match ($type) {
            AuthType::Bearer => $normalized['token'] = (string) ($auth['token'] ?? ''),
            AuthType::Basic => $normalized += [
                'username' => (string) ($auth['username'] ?? ''),
                'password' => (string) ($auth['password'] ?? ''),
            ],
            AuthType::ApiKey => $normalized += [
                'header' => (string) ($auth['header'] ?? 'X-API-Key'),
                'key' => (string) ($auth['key'] ?? ''),
            ],
            AuthType::Custom => $normalized['headers'] = $this->rowsToMap($auth['headers'] ?? []),
            AuthType::None => null,
        };

        return $normalized;
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
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
