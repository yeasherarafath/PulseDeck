<?php

namespace App\Services\Status;

use App\Enums\Status\CheckErrorType;

/**
 * Immutable result of a single HTTP check attempt.
 * Never carries secrets or raw sensitive bodies.
 */
class CheckOutcome
{
    /**
     * @param  array<string, list<string>>  $headers  Response headers (original case => values).
     */
    public function __construct(
        public readonly ?int $httpStatus,
        public readonly string $body,
        public readonly array $headers = [],
        public readonly string $finalUrl = '',
        public readonly int $redirectCount = 0,
        public readonly ?int $responseSize = null,
        public readonly ?int $responseTimeMs = null,
        public readonly ?int $connectTimeMs = null,
        public readonly ?CheckErrorType $errorType = null,
        public readonly ?string $errorMessage = null,
    ) {
        //
    }

    public static function transportError(CheckErrorType $type, string $message, string $url = ''): self
    {
        return new self(
            httpStatus: null,
            body: '',
            finalUrl: $url,
            errorType: $type,
            errorMessage: mb_substr($message, 0, 2000),
        );
    }

    public function failedTransport(): bool
    {
        return $this->httpStatus === null;
    }

    /**
     * First header value, case-insensitive.
     */
    public function header(string $name): ?string
    {
        foreach ($this->headers as $existing => $values) {
            if (strtolower($existing) === strtolower($name)) {
                return $values[0] ?? null;
            }
        }

        return null;
    }
}
