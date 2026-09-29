<?php

namespace App\Services\Status;

use App\Enums\Status\AssertionOperator;
use App\Enums\Status\BodyAssertionType;

/**
 * Runs all configured assertions for a check: HTTP status codes,
 * response-time thresholds, body rules, JSON-path rules, header rules.
 */
class AssertionEngine
{
    public function __construct(private JsonAssertionEngine $json)
    {
        //
    }

    public function run(StatusRequestDefinition $definition, CheckOutcome $outcome): AssertionResult
    {
        $failures = [];
        $warnings = [];

        $this->checkStatusCodes($definition, $outcome, $failures);
        $this->checkResponseTime($definition, $outcome, $failures, $warnings);
        $this->checkBody($definition, $outcome, $failures);
        $this->checkJson($definition, $outcome, $failures);
        $this->checkHeaders($definition, $outcome, $failures);

        return $failures === [] ? AssertionResult::passed($warnings) : AssertionResult::failed($failures, $warnings);
    }

    /**
     * @param  list<array{assertion: string, expected: string, actual: string, message: string}>  $failures
     */
    private function checkStatusCodes(StatusRequestDefinition $definition, CheckOutcome $outcome, array &$failures): void
    {
        if ($outcome->httpStatus === null || $definition->expectedStatusCodes === []) {
            return;
        }

        if (! in_array($outcome->httpStatus, $definition->expectedStatusCodes, true)) {
            $failures[] = [
                'assertion' => 'http_status',
                'expected' => implode(', ', $definition->expectedStatusCodes),
                'actual' => (string) $outcome->httpStatus,
                'message' => 'Expected HTTP status '.implode(', ', $definition->expectedStatusCodes).", received {$outcome->httpStatus}.",
            ];
        }
    }

    /**
     * @param  list<array{assertion: string, expected: string, actual: string, message: string}>  $failures
     * @param  list<array{assertion: string, expected: string, actual: string, message: string}>  $warnings
     */
    private function checkResponseTime(StatusRequestDefinition $definition, CheckOutcome $outcome, array &$failures, array &$warnings): void
    {
        if ($outcome->responseTimeMs === null) {
            return;
        }

        $ms = $outcome->responseTimeMs;

        if ($definition->responseTimeFailure !== null && $ms > $definition->responseTimeFailure) {
            $failures[] = [
                'assertion' => 'response_time',
                'expected' => "< {$definition->responseTimeFailure}ms",
                'actual' => "{$ms}ms",
                'message' => "Response took {$ms}ms, exceeding the failure threshold of {$definition->responseTimeFailure}ms.",
            ];

            return;
        }

        if ($definition->responseTimeWarning !== null && $ms > $definition->responseTimeWarning) {
            $warnings[] = [
                'assertion' => 'response_time',
                'expected' => "< {$definition->responseTimeWarning}ms",
                'actual' => "{$ms}ms",
                'message' => "Response took {$ms}ms, exceeding the warning threshold of {$definition->responseTimeWarning}ms.",
            ];
        }
    }

    /**
     * @param  list<array{assertion: string, expected: string, actual: string, message: string}>  $failures
     */
    private function checkBody(StatusRequestDefinition $definition, CheckOutcome $outcome, array &$failures): void
    {
        foreach ($definition->responseAssertions as $assertion) {
            $type = BodyAssertionType::tryFrom((string) ($assertion['type'] ?? ''));

            if ($type === null) {
                continue;
            }

            $expected = (string) ($assertion['value'] ?? '');
            $passed = match ($type) {
                BodyAssertionType::Contains => str_contains($outcome->body, $expected),
                BodyAssertionType::NotContains => ! str_contains($outcome->body, $expected),
                BodyAssertionType::Equals => $outcome->body === $expected,
                BodyAssertionType::Regex => $this->regexMatches($outcome->body, $expected),
            };

            if (! $passed) {
                $failures[] = [
                    'assertion' => 'body_'.$type->value,
                    'expected' => $expected,
                    'actual' => mb_substr($outcome->body, 0, 500),
                    'message' => "Response body assertion '{$type->label()}' failed.",
                ];
            }
        }
    }

    /**
     * @param  list<array{assertion: string, expected: string, actual: string, message: string}>  $failures
     */
    private function checkJson(StatusRequestDefinition $definition, CheckOutcome $outcome, array &$failures): void
    {
        if ($definition->jsonAssertions === []) {
            return;
        }

        $decoded = json_decode($outcome->body, true);
        $validJson = json_last_error() === JSON_ERROR_NONE;

        foreach ($definition->jsonAssertions as $assertion) {
            $path = (string) ($assertion['path'] ?? '');
            $operator = AssertionOperator::tryFrom((string) ($assertion['operator'] ?? ''));

            if ($path === '' || $operator === null) {
                continue;
            }

            $expected = $assertion['expected'] ?? null;

            if (! $validJson) {
                $failures[] = [
                    'assertion' => 'json_'.$path,
                    'expected' => (string) $expected,
                    'actual' => 'invalid JSON',
                    'message' => "JSON assertion '{$path}' failed: response is not valid JSON.",
                ];

                continue;
            }

            ['found' => $found, 'value' => $value] = $this->json->getValue($decoded, $path);

            if (! $this->json->evaluate($value, $found, $operator, $expected)) {
                $failures[] = [
                    'assertion' => 'json_'.$path,
                    'expected' => $operator->needsExpectedValue() ? (string) $expected : $operator->label(),
                    'actual' => $found ? $this->displayValue($value) : 'missing',
                    'message' => "JSON assertion '{$path} {$operator->value}' failed.",
                ];
            }
        }
    }

    /**
     * @param  list<array{assertion: string, expected: string, actual: string, message: string}>  $failures
     */
    private function checkHeaders(StatusRequestDefinition $definition, CheckOutcome $outcome, array &$failures): void
    {
        foreach ($definition->headerAssertions as $assertion) {
            $name = trim((string) ($assertion['header'] ?? ''));
            $operator = AssertionOperator::tryFrom((string) ($assertion['operator'] ?? ''));

            if ($name === '' || $operator === null) {
                continue;
            }

            $actual = $outcome->header($name);
            $found = $actual !== null;
            $expected = (string) ($assertion['expected'] ?? '');

            $passed = match ($operator) {
                AssertionOperator::Exists => $found,
                AssertionOperator::NotExists => ! $found,
                AssertionOperator::Equals => $found && $actual === $expected,
                AssertionOperator::NotEquals => ! $found || $actual !== $expected,
                AssertionOperator::Contains => $found && str_contains($actual, $expected),
                AssertionOperator::NotContains => ! $found || ! str_contains($actual, $expected),
                default => true,
            };

            if (! $passed) {
                $failures[] = [
                    'assertion' => 'header_'.$name,
                    'expected' => $operator->needsExpectedValue() ? $expected : $operator->label(),
                    'actual' => $found ? $actual : 'missing',
                    'message' => "Response header assertion '{$name} {$operator->value}' failed.",
                ];
            }
        }
    }

    private function regexMatches(string $subject, string $pattern): bool
    {
        if ($pattern === '') {
            return false;
        }

        if (@preg_match($pattern, '') === false) {
            $pattern = '/'.str_replace('/', '\/', $pattern).'/';
        }

        return @preg_match($pattern, $subject) === 1;
    }

    private function displayValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value === null) {
            return 'null';
        }

        if (is_array($value)) {
            return mb_substr(json_encode($value) ?: '', 0, 500);
        }

        return mb_substr((string) $value, 0, 500);
    }
}
