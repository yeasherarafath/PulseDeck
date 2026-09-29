<?php

namespace App\Services\Status;

use App\Enums\Status\AuthType;
use App\Enums\Status\HttpMethod;
use App\Enums\Status\HttpVersion;
use App\Enums\Status\RequestBodyType;

/**
 * Normalized request definition. Every check path (scheduler, Check Now,
 * Test Request) builds one of these via RequestBuilder, so behavior is
 * identical everywhere.
 */
class StatusRequestDefinition
{
    /**
     * @param  array<string, string>  $headers  Merged headers incl. auth-derived ones.
     * @param  array<string, string>  $query  Query parameters.
     * @param  array{type: string, token?: string, username?: string, password?: string, header?: string, key?: string, headers?: array<string, string>}|null  $auth  Raw auth config (may hold secrets — never log).
     * @param  string|array<string, mixed>|null  $body  Raw string or decoded map.
     * @param  list<int>  $expectedStatusCodes
     * @param  list<array<string, mixed>>  $responseAssertions
     * @param  list<array<string, mixed>>  $jsonAssertions
     * @param  list<array<string, mixed>>  $headerAssertions
     * @param  array{0: string, 1: string}|null  $basicAuth  Guzzle [username, password].
     */
    public function __construct(
        public HttpMethod $method,
        public string $url,
        public array $headers = [],
        public array $query = [],
        public ?array $auth = null,
        public RequestBodyType $bodyType = RequestBodyType::None,
        public string|array|null $body = null,
        public ?array $basicAuth = null,
        public int $timeout = 15,
        public int $connectTimeout = 5,
        public bool $followRedirects = true,
        public int $maxRedirects = 5,
        public bool $verifySsl = true,
        public HttpVersion $httpVersion = HttpVersion::Auto,
        public ?string $userAgent = null,
        public array $expectedStatusCodes = [200],
        public ?int $responseTimeWarning = null,
        public ?int $responseTimeFailure = null,
        public array $responseAssertions = [],
        public array $jsonAssertions = [],
        public array $headerAssertions = [],
    ) {
        //
    }

    public function authType(): AuthType
    {
        return AuthType::tryFrom($this->auth['type'] ?? 'none') ?? AuthType::None;
    }

    /**
     * Safe representation for logs, audit trails, and Test Request display.
     *
     * @return array<string, mixed>
     */
    public function forLogging(): array
    {
        return [
            'method' => $this->method->value,
            'url' => $this->url,
            'query' => $this->query,
            'headers' => HeaderPresetManager::redactHeaders($this->headers),
            'auth' => HeaderPresetManager::redactAuth($this->auth),
            'body_type' => $this->bodyType->value,
            'body' => is_string($this->body) ? mb_substr($this->body, 0, 2000) : $this->body,
            'timeout' => $this->timeout,
            'connect_timeout' => $this->connectTimeout,
            'follow_redirects' => $this->followRedirects,
            'max_redirects' => $this->maxRedirects,
            'verify_ssl' => $this->verifySsl,
            'http_version' => $this->httpVersion->value,
        ];
    }
}
