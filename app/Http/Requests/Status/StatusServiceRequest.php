<?php

namespace App\Http\Requests\Status;

use App\Enums\Status\AssertionOperator;
use App\Enums\Status\AuthType;
use App\Enums\Status\BodyAssertionType;
use App\Enums\Status\HttpMethod;
use App\Enums\Status\HttpVersion;
use App\Enums\Status\IntervalUnit;
use App\Enums\Status\RequestBodyType;
use App\Enums\Status\ScheduleType;
use App\Rules\AllowedMonitorUrl;
use App\Services\Status\CheckScheduler;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StatusServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(
            $this->isMethod('post') ? 'status.services.create' : 'status.services.update'
        ) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $serviceId = $this->route('service')?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'alpha_dash', 'max:255', Rule::unique('status_services', 'slug')->ignore($serviceId)],
            'description' => ['nullable', 'string'],
            'group_id' => ['nullable', 'exists:status_service_groups,id'],
            'url' => ['required', 'url', 'max:2048', new AllowedMonitorUrl],
            'method' => ['required', Rule::in(array_column(HttpMethod::cases(), 'value'))],
            'schedule_type' => ['sometimes', Rule::in(array_column(ScheduleType::cases(), 'value'))],
            'check_interval' => ['sometimes', 'integer', 'min:'.CheckScheduler::MIN_SECONDS, 'max:'.CheckScheduler::MAX_SECONDS],
            'interval_value' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:1000000'],
            'interval_unit' => ['sometimes', Rule::in(array_column(IntervalUnit::cases(), 'value'))],
            'cron_expression' => ['sometimes', 'nullable', 'string', 'max:100'],
            'timeout' => ['required', 'integer', 'min:1', 'max:60'],
            'connect_timeout' => ['required', 'integer', 'min:1', 'max:60', 'lte:timeout'],
            'failure_threshold' => ['nullable', 'integer', 'min:1', 'max:100'],
            'recovery_threshold' => ['nullable', 'integer', 'min:1', 'max:100'],
            'follow_redirects' => ['sometimes', 'boolean'],
            'max_redirects' => ['required', 'integer', 'min:0', 'max:20'],
            'verify_ssl' => ['sometimes', 'boolean'],
            'http_version' => ['required', Rule::in(array_column(HttpVersion::cases(), 'value'))],
            'user_agent' => ['nullable', 'string', 'max:255'],

            'headers' => ['sometimes', 'array'],
            'headers.*.name' => ['required_with:headers.*.value', 'nullable', 'string', 'max:255'],
            'headers.*.value' => ['nullable', 'string', 'max:4096'],

            'query' => ['sometimes', 'array'],
            'query.*.name' => ['required_with:query.*.value', 'nullable', 'string', 'max:255'],
            'query.*.value' => ['nullable', 'string', 'max:2048'],

            'auth.type' => ['required', Rule::in(array_column(AuthType::cases(), 'value'))],
            'auth.token' => array_filter([$this->secretRequiredRule('bearer', 'token'), 'nullable', 'string', 'max:4096']),
            'auth.username' => ['required_if:auth.type,basic', 'nullable', 'string', 'max:255'],
            'auth.password' => array_filter([$this->secretRequiredRule('basic', 'password'), 'nullable', 'string', 'max:4096']),
            'auth.header' => ['required_if:auth.type,api_key', 'nullable', 'string', 'max:255'],
            'auth.key' => array_filter([$this->secretRequiredRule('api_key', 'key'), 'nullable', 'string', 'max:4096']),
            'auth.headers' => ['sometimes', 'array'],

            'body_type' => ['required', Rule::in(array_column(RequestBodyType::cases(), 'value'))],
            'body' => [$this->jsonBodyRule()],
            'body_fields' => ['sometimes', 'array'],
            'body_fields.*.name' => ['nullable', 'string', 'max:255'],
            'body_fields.*.value' => ['nullable', 'string', 'max:8192'],

            'expected_statuses' => ['required'],
            'warn_ms' => ['nullable', 'integer', 'min:1', 'max:60000'],
            'fail_ms' => ['nullable', 'integer', 'min:1', 'max:60000', 'gte:warn_ms'],

            'body_assertions' => ['sometimes', 'array'],
            'body_assertions.*.type' => ['required', Rule::in(array_column(BodyAssertionType::cases(), 'value'))],
            'body_assertions.*.value' => ['required', 'string', 'max:2048'],

            'json_assertions' => ['sometimes', 'array'],
            'json_assertions.*.path' => ['required', 'string', 'max:255', 'starts_with:$.'],
            'json_assertions.*.operator' => ['required', Rule::in(array_column(AssertionOperator::cases(), 'value'))],
            'json_assertions.*.expected' => ['nullable', 'string', 'max:2048'],

            'header_assertions' => ['sometimes', 'array'],
            'header_assertions.*.header' => ['required', 'string', 'max:255'],
            'header_assertions.*.operator' => ['required', Rule::in(['equals', 'not_equals', 'contains', 'not_contains', 'exists', 'not_exists'])],
            'header_assertions.*.expected' => ['nullable', 'string', 'max:2048'],

            'is_active' => ['sometimes', 'boolean'],
            'is_public' => ['sometimes', 'boolean'],
            'notify_on_failure' => ['sometimes', 'boolean'],
            'notify_on_recovery' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:999999'],
            'min_failed_checks_down' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * @return array<string, array<int, callable|string>>
     */
    public function after(): array
    {
        return [
            function ($validator): void {
                $this->validateSchedule($validator);
            },
        ];
    }

    private function validateSchedule($validator): void
    {
        $type = (string) ($this->input('schedule_type') ?? 'interval');

        if ($type === ScheduleType::Cron->value) {
            $expression = trim((string) ($this->input('cron_expression') ?? ''));

            if ($expression === '') {
                $validator->errors()->add('cron_expression', 'A cron expression is required for cron scheduling.');
            } elseif (! CheckScheduler::isValidCron($expression)) {
                $validator->errors()->add('cron_expression', 'The cron expression must be a valid 5-part expression (e.g. `*/5 * * * *`).');
            }

            return;
        }

        // Interval mode: resolve value + unit (new UI) or legacy check_interval
        // (API, tests, old payloads) and enforce the global 60s–30d window.
        $value = $this->input('interval_value');
        $unit = IntervalUnit::tryFrom((string) ($this->input('interval_unit') ?? 'minutes'));

        if ($value !== null && $unit !== null) {
            $seconds = ((int) $value) * $unit->seconds();

            if ($seconds < CheckScheduler::MIN_SECONDS) {
                $validator->errors()->add('interval_value', 'The interval must be at least 1 minute (the dispatcher ticks every minute).');
            } elseif ($seconds > CheckScheduler::MAX_SECONDS) {
                $validator->errors()->add('interval_value', 'The interval may not exceed 1 year.');
            }
        } elseif ($this->input('check_interval') !== null) {
            $seconds = (int) $this->input('check_interval');

            if ($seconds < CheckScheduler::MIN_SECONDS || $seconds > CheckScheduler::MAX_SECONDS) {
                $validator->errors()->add('check_interval', 'The check interval must be between 1 minute and 1 year.');
            }
        } else {
            $validator->errors()->add('interval_value', 'An interval value is required for interval scheduling.');
        }
    }

    protected function prepareForValidation(): void
    {
        // Backward compatibility: legacy payloads (API/tests) send only
        // check_interval with no schedule fields. Expand them so the new
        // interval UI validates without breaking existing callers.
        if (! $this->has('schedule_type') && $this->has('check_interval')) {
            $this->merge(['schedule_type' => ScheduleType::Interval->value]);
        }

        if ($this->has('check_interval') && ! $this->has('interval_value')) {
            $split = CheckScheduler::fromSeconds((int) $this->input('check_interval', 300));

            $this->merge([
                'interval_value' => $split['value'],
                'interval_unit' => $split['unit']->value,
            ]);
        }

        if (! $this->has('schedule_type')) {
            $this->merge(['schedule_type' => ScheduleType::Interval->value]);
        }

        // Drop fully-blank repeatable rows so empty template rows never
        // trip validation (works for form posts and JSON test payloads).
        foreach (['headers', 'query', 'body_fields', 'body_assertions', 'json_assertions', 'header_assertions'] as $key) {
            $rows = $this->input($key);

            if (is_array($rows)) {
                $this->merge([$key => array_values(array_filter($rows, fn ($row) => $this->rowUsed($row)))]);
            }
        }

        $authHeaders = $this->input('auth.headers');

        if (is_array($authHeaders)) {
            $this->merge(['auth.headers' => array_values(array_filter($authHeaders, fn ($row) => $this->rowUsed($row)))]);
        }
    }

    private function rowUsed(mixed $row): bool
    {
        if (! is_array($row)) {
            return false;
        }

        foreach ($row as $key => $value) {
            if (in_array($key, ['type', 'operator'], true)) {
                continue;
            }

            if (is_string($value) && trim($value) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * Secret fields show "leave blank to keep" on edit: require a value
     * only when creating or when nothing is stored yet. Blank submissions
     * on update keep the stored secret (see keepUnchangedSecrets).
     */
    private function secretRequiredRule(string $type, string $key): ?string
    {
        $service = $this->route('service');

        $stored = $service && is_array($service->authentication)
            ? ($service->authentication[$key] ?? null)
            : null;

        if ($this->isMethod('post') || blank($stored)) {
            return 'required_if:auth.type,'.$type;
        }

        return null;
    }

    private function jsonBodyRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (($this->input('body_type') !== RequestBodyType::Json->value)) {
                return;
            }

            if (! is_string($value) || trim($value) === '') {
                $fail('A JSON body is required for the JSON body type.');

                return;
            }

            json_decode($value);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $fail('The body must be valid JSON.');
            }
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function validatedForService(): array
    {
        $validated = $this->validated();

        // Normalize checkbox absence (unchecked switches are not submitted).
        foreach (['follow_redirects', 'verify_ssl', 'is_active', 'is_public', 'notify_on_failure', 'notify_on_recovery'] as $flag) {
            $validated[$flag] = (bool) ($validated[$flag] ?? false);
        }

        return $validated;
    }
}
