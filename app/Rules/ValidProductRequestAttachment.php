<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidProductRequestAttachment implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        (new ValidUploadFile('product_attachments'))->validate($attribute, $value, $fail);
    }
}
