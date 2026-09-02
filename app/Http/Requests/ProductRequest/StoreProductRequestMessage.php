<?php

namespace App\Http\Requests\ProductRequest;

use App\Support\UploadPolicy;
use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequestMessage extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('product-request.reply');
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'min:2', 'max:10000'],
            'attachments' => UploadPolicy::collectionRules('product_attachments'),
            'attachments.*' => UploadPolicy::fileRules('product_attachments', true),
            'attachments_upload_uuids' => ['nullable', 'array', 'max:3'],
            'attachments_upload_uuids.*' => UploadPolicy::tokenRules('product_attachments'),
        ];
    }
}
