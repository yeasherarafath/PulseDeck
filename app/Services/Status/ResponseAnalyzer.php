<?php

namespace App\Services\Status;

use App\Enums\Status\CheckErrorType;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\TooManyRedirectsException;
use Throwable;

/**
 * Classifies transport failures so admins see WHAT broke
 * (DNS, timeout, TLS, …) instead of a generic "request failed".
 */
class ResponseAnalyzer
{
    public function fromThrowable(Throwable $exception, string $url = ''): CheckOutcome
    {
        return CheckOutcome::transportError(
            $this->classify($exception),
            $this->shortMessage($exception),
            $url,
        );
    }

    public function classify(Throwable $exception): CheckErrorType
    {
        if ($exception instanceof SsrfBlockedException) {
            return CheckErrorType::SsrfBlocked;
        }

        if ($exception instanceof TooManyRedirectsException) {
            return CheckErrorType::Redirect;
        }

        $message = strtolower($exception->getMessage());

        if ($this->isDnsFailure($exception, $message)) {
            return CheckErrorType::Dns;
        }

        if ($this->isTlsFailure($message)) {
            return CheckErrorType::Tls;
        }

        if ($this->isTimeout($message)) {
            return CheckErrorType::Timeout;
        }

        if ($exception instanceof ConnectException) {
            return CheckErrorType::Connection;
        }

        return CheckErrorType::Unknown;
    }

    public function fromHttpStatus(int $status): ?CheckErrorType
    {
        if ($status >= 500) {
            return CheckErrorType::Http5xx;
        }

        if ($status >= 400) {
            return CheckErrorType::Http4xx;
        }

        return null;
    }

    private function isDnsFailure(Throwable $exception, string $message): bool
    {
        if (! $exception instanceof ConnectException) {
            return false;
        }

        return str_contains($message, 'curl error 6')
            || str_contains($message, 'could not resolve')
            || str_contains($message, 'name or service not known')
            || str_contains($message, 'getaddrinfo');
    }

    private function isTlsFailure(string $message): bool
    {
        foreach (['curl error 35', 'curl error 51', 'curl error 53', 'curl error 54', 'curl error 58', 'curl error 59', 'curl error 60', 'curl error 64', 'curl error 77', 'ssl', 'tls', 'certificate'] as $needle) {
            if (str_contains($message, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function isTimeout(string $message): bool
    {
        return str_contains($message, 'curl error 28')
            || str_contains($message, 'timed out')
            || str_contains($message, 'timeout');
    }

    private function shortMessage(Throwable $exception): string
    {
        $message = $exception->getMessage();

        // Strip absolute paths that could leak server layout.
        $message = str_replace([base_path(), app_path(), storage_path()], ['[app]', '[app]', '[storage]'], $message);

        return mb_substr(trim($message) === '' ? get_class($exception) : $message, 0, 1000);
    }
}
