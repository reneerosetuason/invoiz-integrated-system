<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PhilippinePhone implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $digits = preg_replace('/[\s\-()]/', '', (string) $value);

        if (!preg_match('/^(09\d{9}|\+639\d{9})$/', $digits)) {
            $fail('Please enter a valid Philippine mobile number.');
        }
    }

    public static function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $digits = preg_replace('/[\s\-()]/', '', trim($value));

        if (preg_match('/^\+639(\d{9})$/', $digits, $m)) {
            return '09' . $m[1];
        }

        return $digits;
    }
}
