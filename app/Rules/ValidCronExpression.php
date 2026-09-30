<?php

namespace App\Rules;

use App\Services\Status\CheckScheduler;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidCronExpression implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || trim($value) === '') {
            $fail('The :attribute field is required.');

            return;
        }

        if (! CheckScheduler::isValidCron($value)) {
            $fail('The :attribute must be a valid 5-part cron expression (e.g. `*/5 * * * *`).');
        }
    }
}
