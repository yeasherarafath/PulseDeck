<?php

namespace App\Http\Requests\Status;

class TestServiceRequest extends StatusServiceRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('status.monitoring.run') ?? false;
    }
}
