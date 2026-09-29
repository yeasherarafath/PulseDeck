<?php

namespace App\Http\Requests\Status;

class TestServiceRequest extends StatusServiceRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('status.monitoring.run') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = parent::rules();

        // A test run never persists: slug uniqueness is irrelevant and would
        // wrongly flag the service's own slug on the edit page.
        $rules['slug'] = ['nullable', 'alpha_dash', 'max:255'];

        return $rules;
    }
}
