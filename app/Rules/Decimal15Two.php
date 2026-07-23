<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class Decimal15Two implements ValidationRule
{
    public const MAX = '9999999999999.99';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (self::normalizeNonNegative($value) === null) {
            $fail('Nilai :attribute harus berupa desimal non-negatif, maksimal 13 digit dan 2 angka pecahan.');
        }
    }

    public static function normalizeNonNegative(mixed $value): ?string
    {
        if ((! is_string($value) && ! is_int($value) && ! is_float($value)) || is_bool($value)) {
            return null;
        }

        $value = (string) $value;

        if (preg_match('/\A\d{1,13}(?:\.\d{1,2})?\z/', $value) !== 1) {
            return null;
        }

        return bccomp($value, self::MAX, 2) <= 0 ? $value : null;
    }

    public static function fitsSigned(string $value): bool
    {
        if (preg_match('/\A-?\d+(?:\.\d{1,2})?\z/', $value) !== 1) {
            return false;
        }

        return bccomp($value, self::MAX, 2) <= 0
            && bccomp($value, '-'.self::MAX, 2) >= 0;
    }
}
