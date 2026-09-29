<?php

namespace App\Services\Status;

use App\Jobs\Status\CheckService;
use App\Models\Status\StatusAuditLog;
use App\Models\Status\StatusService;
use App\Models\User;
use Illuminate\Support\Str;
use Throwable;

/**
 * Service lifecycle: create / update / delete / pause + manual Test Request.
 * Controllers validate; this class persists, audits (redacted), and runs checks.
 */
class StatusServiceManager
{
    public function __construct(
        private RequestBuilder $builder,
        private HttpChecker $checker,
        private AssertionEngine $assertions,
        private StatusCalculator $calculator,
        private MaintenanceManager $maintenance,
    ) {
        //
    }

    /**
     * @param  array<string, mixed>  $input  Validated form data.
     */
    public function create(array $input, User $user): StatusService
    {
        $attributes = $this->builder->normalizeServiceAttributes($input);

        if (blank($attributes['slug'])) {
            $attributes['slug'] = $this->uniqueSlug($attributes['name']);
        }

        $service = StatusService::create($attributes);

        StatusAuditLog::record('service.created', $service, null, $this->auditable($attributes));

        return $service;
    }

    /**
     * @param  array<string, mixed>  $input  Validated form data.
     */
    public function update(StatusService $service, array $input, User $user): StatusService
    {
        $attributes = $this->builder->normalizeServiceAttributes($input);

        if (blank($attributes['slug'])) {
            $attributes['slug'] = $service->slug;
        }

        $attributes = $this->keepUnchangedSecrets($service, $attributes);

        $before = $this->auditable($service->getAttributes());

        $service->fill($attributes)->save();

        StatusAuditLog::record('service.updated', $service->fresh(), $before, $this->auditable($service->fresh()->getAttributes()));

        return $service->fresh();
    }

    public function delete(StatusService $service, User $user): void
    {
        StatusAuditLog::record('service.deleted', $service, $this->auditable($service->getAttributes()), null);

        $service->delete();
    }

    public function toggleActive(StatusService $service, User $user): StatusService
    {
        $service->forceFill(['is_active' => ! $service->is_active])->save();

        StatusAuditLog::record(
            $service->is_active ? 'service.resumed' : 'service.paused',
            $service->fresh(),
            ['is_active' => ! $service->is_active],
            ['is_active' => $service->is_active]
        );

        return $service->fresh();
    }

    public function checkNow(StatusService $service): void
    {
        $service->forceFill(['next_check_at' => now()])->save();

        CheckService::dispatch($service->id, true);
    }

    /**
     * Manual Test Request. Never writes check history.
     *
     * @param  array<string, mixed>  $input  Validated form data (unsaved test).
     * @return array{ok: bool, result: string, outcome: array<string, mixed>, assertions: array{passed: bool, failures: array, warnings: array}, error: ?string}
     */
    public function testRequest(?StatusService $service, array $input): array
    {
        try {
            $definition = $service
                ? $this->builder->fromService($service->fresh())
                : $this->builder->fromArray($input);

            $this->builder->validateDefinition($definition);
        } catch (Throwable $exception) {
            return [
                'ok' => false,
                'result' => 'failed',
                'outcome' => [],
                'assertions' => ['passed' => false, 'failures' => [], 'warnings' => []],
                'error' => $exception->getMessage(),
            ];
        }

        $outcome = $this->checker->check($definition);
        $assertionResult = $this->assertions->run($definition, $outcome);
        $maintenanceActive = $service ? $this->maintenance->isUnderMaintenance($service) : false;
        $result = $this->calculator->calculate($definition, $outcome, $assertionResult, $maintenanceActive);

        return [
            'ok' => true,
            'result' => $result->value,
            'outcome' => [
                'request' => $definition->forLogging(),
                'requested_at' => now()->toDateTimeString(),
                'http_status' => $outcome->httpStatus,
                'response_time_ms' => $outcome->responseTimeMs,
                'connect_time_ms' => $outcome->connectTimeMs,
                'final_url' => $outcome->finalUrl,
                'redirect_count' => $outcome->redirectCount,
                'response_size' => $outcome->responseSize,
                'error' => $outcome->errorType?->label(),
                'error_message' => $outcome->errorMessage,
            ],
            'assertions' => $assertionResult->toArray(),
            'error' => null,
        ];
    }

    /**
     * Blank submitted values for sensitive headers keep the stored secret.
     * (Edit forms never prefill secrets; empty means "unchanged".)
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function keepUnchangedSecrets(StatusService $service, array $attributes): array
    {
        $old = $service->request_headers ?? [];

        if (! is_array($old)) {
            return $attributes;
        }

        $headers = $attributes['request_headers'] ?? [];

        foreach ($headers as $name => $value) {
            if ($value === '' && array_key_exists($name, $old) && HeaderPresetManager::isSensitive($name)) {
                $headers[$name] = $old[$name];
            }
        }

        $attributes['request_headers'] = $headers;

        // Auth secrets: blank token/password/key keeps the stored one.
        $oldAuth = is_array($service->authentication) ? $service->authentication : [];
        $newAuth = $attributes['authentication'] ?? [];

        if (($newAuth['type'] ?? 'none') === ($oldAuth['type'] ?? 'none')) {
            foreach (['token', 'password', 'key'] as $secretKey) {
                if (array_key_exists($secretKey, $newAuth) && $newAuth[$secretKey] === '' && isset($oldAuth[$secretKey])) {
                    $newAuth[$secretKey] = $oldAuth[$secretKey];
                }
            }

            // Custom auth headers: blank values keep stored secrets.
            if (($newAuth['type'] ?? 'none') === 'custom' && isset($oldAuth['headers']) && is_array($oldAuth['headers'])) {
                foreach (($newAuth['headers'] ?? []) as $name => $value) {
                    if ($value === '' && array_key_exists($name, $oldAuth['headers'])) {
                        $newAuth['headers'][$name] = $oldAuth['headers'][$name];
                    }
                }
            }

            $attributes['authentication'] = $newAuth;
        }

        return $attributes;
    }

    /**
     * Audit-safe snapshot: header/auth VALUES replaced by names/presence.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function auditable(array $attributes): array
    {
        unset($attributes['request_headers'], $attributes['authentication'], $attributes['request_body']);

        return $attributes;
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'service';
        $slug = $base;
        $counter = 2;

        while (StatusService::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$counter++;
        }

        return $slug;
    }
}
