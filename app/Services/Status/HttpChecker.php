<?php

namespace App\Services\Status;

use App\Enums\Status\CheckErrorType;
use App\Enums\Status\HttpVersion;
use App\Enums\Status\RequestBodyType;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use GuzzleHttp\TransferStats;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Throwable;

/**
 * Executes a StatusRequestDefinition over HTTP.
 *
 * Redirects are followed manually (never by the HTTP client itself) so the
 * SSRF guard validates EVERY target, not just the original URL.
 */
class HttpChecker
{
    public const MAX_RESPONSE_BYTES = 1024 * 1024;

    /** @var list<int> */
    private const REDIRECT_STATUSES = [301, 302, 303, 307, 308];

    public function __construct(
        private SsrfGuard $guard,
        private ResponseAnalyzer $analyzer,
    ) {
        //
    }

    public function check(StatusRequestDefinition $definition): CheckOutcome
    {
        $url = $definition->url;
        $redirects = 0;

        while (true) {
            try {
                $this->guard->assertSafeUrl($url);
            } catch (SsrfBlockedException $exception) {
                return CheckOutcome::transportError(CheckErrorType::SsrfBlocked, $exception->getMessage(), $url);
            }

            try {
                $outcome = $this->attempt($definition, $url, $redirects);
            } catch (Throwable $exception) {
                return $this->analyzer->fromThrowable($exception, $url);
            }

            $location = $outcome->httpStatus !== null && in_array($outcome->httpStatus, self::REDIRECT_STATUSES, true)
                ? $this->firstHeader($outcome, 'Location')
                : null;

            if ($location === null || $location === '' || ! $definition->followRedirects) {
                return $outcome;
            }

            if ($redirects >= $definition->maxRedirects) {
                return new CheckOutcome(
                    httpStatus: $outcome->httpStatus,
                    body: '',
                    headers: $outcome->headers,
                    finalUrl: $url,
                    redirectCount: $redirects,
                    responseTimeMs: $outcome->responseTimeMs,
                    connectTimeMs: $outcome->connectTimeMs,
                    errorType: CheckErrorType::Redirect,
                    errorMessage: "Redirect limit of {$definition->maxRedirects} exceeded at {$url}.",
                );
            }

            $url = $this->resolveUrl($url, $location);
            $redirects++;
        }
    }

    private function attempt(StatusRequestDefinition $definition, string $url, int $redirects): CheckOutcome
    {
        $connectMs = null;
        $totalMs = null;

        $request = Http::withHeaders($definition->headers)
            ->timeout($definition->timeout)
            ->connectTimeout($definition->connectTimeout)
            ->withOptions([
                'allow_redirects' => false,
                'verify' => $definition->verifySsl,
                'http_errors' => false,
                'on_stats' => function (TransferStats $stats) use (&$connectMs, &$totalMs): void {
                    $handler = $stats->getHandlerStats();
                    $connectMs = isset($handler['connect_time']) ? (int) round($handler['connect_time'] * 1000) : null;
                    $totalMs = isset($handler['total_time']) ? (int) round($handler['total_time'] * 1000) : null;
                },
            ]);

        if ($definition->httpVersion !== HttpVersion::Auto) {
            $request->withOptions(['version' => (float) $definition->httpVersion->value]);
        }

        if ($definition->basicAuth !== null) {
            $request->withOptions(['auth' => $definition->basicAuth]);
        }

        $startedAt = microtime(true);
        $response = $request->send($definition->method->value, $url, $this->guzzleOptions($definition));
        $measuredMs = (int) round((microtime(true) - $startedAt) * 1000);

        $status = $response->status();
        $headers = $response->headers();
        $body = (string) $response->body();

        $declaredSize = $this->firstHeaderValue($headers, 'Content-Length');

        if ($declaredSize !== null && is_numeric($declaredSize) && (int) $declaredSize > self::MAX_RESPONSE_BYTES) {
            return $this->oversized($url, $redirects, $status, $headers, (int) $declaredSize, $totalMs ?? $measuredMs, $connectMs);
        }

        $size = strlen($body);

        if ($size > self::MAX_RESPONSE_BYTES) {
            return $this->oversized($url, $redirects, $status, $headers, $size, $totalMs ?? $measuredMs, $connectMs);
        }

        return new CheckOutcome(
            httpStatus: $status,
            body: $body,
            headers: $headers,
            finalUrl: $url,
            redirectCount: $redirects,
            responseSize: $size,
            responseTimeMs: $totalMs ?? $measuredMs,
            connectTimeMs: $connectMs,
        );
    }

    /**
     * @param  array<string, list<string>>  $headers
     */
    private function oversized(string $url, int $redirects, int $status, array $headers, int $size, int $totalMs, ?int $connectMs): CheckOutcome
    {
        return new CheckOutcome(
            httpStatus: $status,
            body: '',
            headers: $headers,
            finalUrl: $url,
            redirectCount: $redirects,
            responseSize: $size,
            responseTimeMs: $totalMs,
            connectTimeMs: $connectMs,
            errorType: CheckErrorType::Oversized,
            errorMessage: 'Response exceeded the '.(self::MAX_RESPONSE_BYTES / 1024).' KB read limit and was discarded.',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function guzzleOptions(StatusRequestDefinition $definition): array
    {
        return match ($definition->bodyType) {
            RequestBodyType::Json => ['json' => $this->decodedBody($definition)],
            RequestBodyType::Form => ['multipart' => $this->multipart($definition)],
            RequestBodyType::Urlencoded => ['form_params' => $this->decodedBody($definition)],
            RequestBodyType::Raw => ['body' => is_string($definition->body) ? $definition->body : ''],
            RequestBodyType::None => [],
        };
    }

    /**
     * @return array<string, string>
     */
    private function decodedBody(StatusRequestDefinition $definition): array
    {
        if (is_array($definition->body)) {
            return $definition->body;
        }

        $decoded = json_decode((string) $definition->body, true);

        if (! is_array($decoded)) {
            throw new InvalidArgumentException('Request body is not valid JSON.');
        }

        return $decoded;
    }

    /**
     * @return list<array{name: string, contents: string}>
     */
    private function multipart(StatusRequestDefinition $definition): array
    {
        $parts = [];

        foreach ($this->decodedBody($definition) as $name => $value) {
            $parts[] = ['name' => $name, 'contents' => $value];
        }

        return $parts;
    }

    private function resolveUrl(string $base, string $location): string
    {
        return (string) UriResolver::resolve(new Uri($base), new Uri(trim($location)));
    }

    private function firstHeader(CheckOutcome $outcome, string $name): ?string
    {
        return $outcome->header($name);
    }

    /**
     * @param  array<string, list<string>>  $headers
     */
    private function firstHeaderValue(array $headers, string $name): ?string
    {
        foreach ($headers as $existing => $values) {
            if (strtolower($existing) === strtolower($name)) {
                return $values[0] ?? null;
            }
        }

        return null;
    }
}
