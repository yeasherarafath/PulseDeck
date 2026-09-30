<?php

namespace Tests\Unit\Status;

use App\Enums\Status\CheckErrorType;
use App\Enums\Status\CheckResultStatus;
use App\Enums\Status\HttpMethod;
use App\Services\Status\AssertionResult;
use App\Services\Status\CheckOutcome;
use App\Services\Status\StatusCalculator;
use App\Services\Status\StatusRequestDefinition;
use PHPUnit\Framework\TestCase;

class StatusCalculatorTest extends TestCase
{
    /** @param list<int> $expected */
    private function definition(array $expected = [200], ?int $failMs = null): StatusRequestDefinition
    {
        return new StatusRequestDefinition(
            method: HttpMethod::Get,
            url: 'https://example.com',
            expectedStatusCodes: $expected,
            responseTimeFailure: $failMs,
        );
    }

    private function calc(CheckOutcome $outcome, ?StatusRequestDefinition $definition = null, bool $maintenance = false): CheckResultStatus
    {
        return (new StatusCalculator)->calculate(
            $definition ?? $this->definition(),
            $outcome,
            AssertionResult::passed(),
            $maintenance,
        );
    }

    public function test_200_is_operational(): void
    {
        $this->assertSame(CheckResultStatus::Operational, $this->calc(new CheckOutcome(200, 'ok', responseTimeMs: 50)));
    }

    public function test_unexpected_status_codes_fail(): void
    {
        $this->assertSame(CheckResultStatus::Failed, $this->calc(new CheckOutcome(500, '')));
        $this->assertSame(CheckResultStatus::Failed, $this->calc(new CheckOutcome(404, '')));
    }

    public function test_expected_204_is_operational(): void
    {
        $this->assertSame(CheckResultStatus::Operational, $this->calc(new CheckOutcome(204, ''), $this->definition([204])));
    }

    public function test_transport_error_fails(): void
    {
        $this->assertSame(CheckResultStatus::Failed, $this->calc(CheckOutcome::transportError(CheckErrorType::Timeout, 'timed out')));
    }

    public function test_maintenance_wins_over_everything(): void
    {
        $this->assertSame(CheckResultStatus::Maintenance, $this->calc(new CheckOutcome(500, ''), maintenance: true));
    }

    public function test_slower_than_failure_threshold_fails(): void
    {
        $this->assertSame(CheckResultStatus::Failed, $this->calc(new CheckOutcome(200, '', responseTimeMs: 5000), $this->definition(failMs: 1000)));
    }

    public function test_error_type_classification(): void
    {
        $calculator = new StatusCalculator;
        $passed = AssertionResult::passed();

        $this->assertSame(CheckErrorType::Http5xx, $calculator->errorTypeFor(new CheckOutcome(503, ''), $passed));
        $this->assertSame(CheckErrorType::Http4xx, $calculator->errorTypeFor(new CheckOutcome(404, ''), $passed));
        $this->assertNull($calculator->errorTypeFor(new CheckOutcome(200, ''), $passed));
    }
}
