<?php

namespace App\Rules;

use App\Support\UploadPolicy;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

final class TotalUploadSize implements ValidationRule
{
    public function __construct(private readonly string $policy) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value)) {
            return;
        }

        $policy = UploadPolicy::get($this->policy);
        $total = $this->sumBytes($value);
        $maxBytes = (int) $policy['max_total_kilobytes'] * 1024;

        if ($total > $maxBytes) {
            $megabytes = rtrim(rtrim(number_format($maxBytes / 1048576, 1, ',', '.'), '0'), ',');
            $fail("Total ukuran file maksimal {$megabytes} MB.");
        }
    }

    /** @param array<array-key, mixed> $values */
    private function sumBytes(array $values): int
    {
        return array_sum(array_map(function (mixed $value): int {
            if ($value instanceof UploadedFile) {
                return max(0, (int) $value->getSize());
            }

            return is_array($value) ? $this->sumBytes($value) : 0;
        }, $values));
    }
}
