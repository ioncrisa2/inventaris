<?php

namespace App\Http\Requests\ProductRequest;

use App\Enums\ProductRequestMessageVisibility;
use App\Rules\ValidProductRequestAttachment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOwnerProductRequestMessage extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isSystemOwner();
    }

    public function rules(): array
    {
        $attachments = config('product_requests.attachments');

        return [
            'visibility' => ['required', Rule::enum(ProductRequestMessageVisibility::class)],
            'body' => ['required', 'string', 'min:2', 'max:10000'],
            'attachments' => [
                'nullable',
                Rule::prohibitedIf(fn () => $this->input('visibility') === ProductRequestMessageVisibility::Internal->value),
                'array',
                'max:'.$attachments['max_files_per_submission'],
            ],
            'attachments.*' => [
                'file',
                new ValidProductRequestAttachment,
                'max:'.$attachments['max_file_kilobytes'],
            ],
        ];
    }
}
