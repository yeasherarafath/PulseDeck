<?php

namespace App\Console\Commands\Status;

use App\Models\Status\StatusService;
use App\Services\Status\AssertionEngine;
use App\Services\Status\HttpChecker;
use App\Services\Status\MaintenanceManager;
use App\Services\Status\RequestBuilder;
use App\Services\Status\StatusCalculator;
use Illuminate\Console\Command;
use Throwable;

/**
 * CLI debugging: run one check for a service without writing history.
 * Secrets are always redacted in the output.
 */
class StatusTest extends Command
{
    protected $signature = 'status:test {service : Service slug or ID.}';

    protected $description = 'Run a one-off check for a service and dump the redacted result.';

    public function handle(
        RequestBuilder $builder,
        HttpChecker $checker,
        AssertionEngine $assertions,
        StatusCalculator $calculator,
        MaintenanceManager $maintenance,
    ): int {
        $identifier = $this->argument('service');

        $service = StatusService::where('slug', $identifier)->orWhere('id', $identifier)->first();

        if (! $service) {
            $this->error("Service [{$identifier}] not found.");

            return self::FAILURE;
        }

        try {
            $definition = $builder->fromService($service);
            $builder->validateDefinition($definition);
        } catch (Throwable $exception) {
            $this->error('Invalid configuration: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->line("{$definition->method->value} {$definition->url}");

        $outcome = $checker->check($definition);
        $result = $assertions->run($definition, $outcome);
        $status = $calculator->calculate($definition, $outcome, $result, $maintenance->isUnderMaintenance($service));

        $this->table(
            ['Metric', 'Value'],
            [
                ['HTTP status', $outcome->httpStatus ?? '—'],
                ['Result', $status->value],
                ['Response time', $outcome->responseTimeMs !== null ? $outcome->responseTimeMs.' ms' : '—'],
                ['Connect time', $outcome->connectTimeMs !== null ? $outcome->connectTimeMs.' ms' : '—'],
                ['Final URL', $outcome->finalUrl !== '' ? $outcome->finalUrl : '—'],
                ['Redirects', $outcome->redirectCount],
                ['Error', $outcome->errorType?->label() ?? '—'],
            ],
        );

        foreach ($result->failures as $failure) {
            $this->error("FAIL {$failure['assertion']}: {$failure['message']} (expected: {$failure['expected']}, actual: {$failure['actual']})");
        }

        foreach ($result->warnings as $warning) {
            $this->warn("WARN {$warning['assertion']}: {$warning['message']}");
        }

        if ($result->passed && $result->warnings === []) {
            $this->info('All assertions passed.');
        }

        return self::SUCCESS;
    }
}
