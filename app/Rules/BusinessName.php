<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class BusinessName implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $name = (string) $value;

        if ($name === '') {
            return;
        }

        if (preg_match('/\p{Cc}/u', $name)) {
            $fail('The :attribute must not contain control characters or line breaks.');

            return;
        }

        if (! preg_match("/^[\p{L}\p{M}\p{N}][\p{L}\p{M}\p{N}\s&'’.,()+\-\/#]*$/u", $name)) {
            $fail('The :attribute may contain letters, numbers, and common business punctuation only.');
        }
    }
}
