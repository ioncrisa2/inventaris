<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

class ValidProductRequestAttachment implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || ! $value->isValid()) {
            $fail('Lampiran gagal diunggah.');

            return;
        }

        $extension = strtolower($value->getClientOriginalExtension());
        $mimeType = strtolower((string) $value->getMimeType());
        $allowed = config('product_requests.attachments.mime_by_extension', []);

        if (! array_key_exists($extension, $allowed)
            || ! in_array($mimeType, (array) $allowed[$extension], true)) {
            $fail('Lampiran harus berupa PDF, JPG, PNG, WEBP, atau TXT dengan tipe isi yang sesuai.');
        }
    }
}
