<?php

namespace App\Http\Requests\Status;

use App\Enums\Status\AssertionOperator;
use App\Enums\Status\AuthType;
use App\Enums\Status\BodyAssertionType;
use App\Enums\Status\HttpMethod;
use App\Enums\Status\HttpVersion;
use App\Enums\Status\RequestBodyType;
use App\Rules\AllowedMonitorUrl;
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
            'check_interval' => ['required', 'integer', 'min:60', 'max:86400'],
            'timeout' => ['required', 'integer', 'min:1', 'max:60'],
            'connect_timeout' => ['required', 'integer', 'min:1', 'max:60'],
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
            'auth.token' => ['required_if:auth.type,bearer', 'nullable', 'string', 'max:4096'],
            'auth.username' => ['required_if:auth.type,basic', 'nullable', 'string', 'max:255'],
            'auth.password' => ['required_if:auth.type,basic', 'nullable', 'string', 'max:4096'],
            'auth.header' => ['required_if:auth.type,api_key', 'nullable', 'string', 'max:255'],
            'auth.key' => ['required_if:auth.type,api_key', 'nullable', 'string', 'max:4096'],
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
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:999999'],
        ];
    }

    protected function prepareForValidation(): void
    {
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
        foreach (['follow_redirects', 'verify_ssl', 'is_active', 'is_public'] as $flag) {
            $validated[$flag] = (bool) ($validated[$flag] ?? false);
        }

        return $validated;
    }
}
