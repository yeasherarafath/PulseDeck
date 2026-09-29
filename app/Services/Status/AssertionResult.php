<?php

namespace App\Services\Status;

/**
 * Assertion run result. Warnings (e.g. slow but passing) degrade a service
 * without failing it; failures mark the check as failed.
 */
class AssertionResult
{
    /**
     * @param  list<array{assertion: string, expected: string, actual: string, message: string}>  $failures
     * @param  list<array{assertion: string, expected: string, actual: string, message: string}>  $warnings
     */
    public function __construct(
        public readonly bool $passed,
        public readonly array $failures = [],
        public readonly array $warnings = [],
    ) {
        //
    }

    public static function passed(array $warnings = []): self
    {
        return new self(true, [], $warnings);
    }

    /**
     * @param  list<array{assertion: string, expected: string, actual: string, message: string}>  $failures
     * @param  list<array{assertion: string, expected: string, actual: string, message: string}>  $warnings
     */
    public static function failed(array $failures, array $warnings = []): self
    {
        return new self(false, $failures, $warnings);
    }

    /**
     * @return array{passed: bool, failures: array, warnings: array}
     */
    public function toArray(): array
    {
        return [
            'passed' => $this->passed,
            'failures' => $this->failures,
            'warnings' => $this->warnings,
        ];
    }
}
