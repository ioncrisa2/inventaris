<?php

namespace App\Http\Requests\ProductRequest;

use App\Rules\ValidProductRequestAttachment;
use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequestMessage extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('product-request.reply');
    }

    public function rules(): array
    {
        $attachments = config('product_requests.attachments');

        return [
            'body' => ['required', 'string', 'min:2', 'max:10000'],
            'attachments' => ['nullable', 'array', 'max:'.$attachments['max_files_per_submission']],
            'attachments.*' => [
                'file',
                new ValidProductRequestAttachment,
                'max:'.$attachments['max_file_kilobytes'],
            ],
        ];
    }
}
