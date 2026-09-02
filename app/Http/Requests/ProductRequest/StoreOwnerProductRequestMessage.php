<?php

namespace App\Http\Requests\ProductRequest;

use App\Enums\ProductRequestMessageVisibility;
use App\Rules\TotalUploadSize;
use App\Support\UploadPolicy;
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
        return [
            'visibility' => ['required', Rule::enum(ProductRequestMessageVisibility::class)],
            'body' => ['required', 'string', 'min:2', 'max:10000'],
            'attachments' => [
                'nullable',
                Rule::prohibitedIf(fn () => $this->input('visibility') === ProductRequestMessageVisibility::Internal->value),
                'array',
                'max:'.UploadPolicy::get('product_attachments')['max_files'],
                new TotalUploadSize('product_attachments'),
            ],
            'attachments.*' => UploadPolicy::fileRules('product_attachments', true),
            'attachments_upload_uuids' => [
                'nullable',
                Rule::prohibitedIf(fn () => $this->input('visibility') === ProductRequestMessageVisibility::Internal->value),
                'array',
                'max:3',
            ],
            'attachments_upload_uuids.*' => UploadPolicy::tokenRules('product_attachments'),
        ];
    }
}
