<?php

namespace App\Support;

final class NameNormalizer
{
    /**
     * Trim surrounding whitespace and collapse repeated spaces without
     * removing control characters; the validation rules reject those.
     */
    public static function clean(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return preg_replace('/ {2,}/u', ' ', $value) ?? $value;
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<int, string>  $keys
     * @return array<string, mixed>
     */
    public static function cleanKeys(array $input, array $keys): array
    {
        foreach ($keys as $key) {
            if (is_string($input[$key] ?? null)) {
                $input[$key] = self::clean($input[$key]);
            }
        }

        return $input;
    }
}
