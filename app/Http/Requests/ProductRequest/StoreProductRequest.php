<?php

namespace App\Http\Requests\ProductRequest;

use App\Enums\ProductRequestPriority;
use App\Enums\ProductRequestType;
use App\Models\ProductRequest;
use App\Support\UploadPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', ProductRequest::class);
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(ProductRequestType::class)],
            'module' => ['nullable', Rule::in(array_keys(config('product_requests.modules')))],
            'title' => ['required', 'string', 'min:5', 'max:180'],
            'description' => ['required', 'string', 'min:10', 'max:10000'],
            'requester_priority' => ['required', Rule::enum(ProductRequestPriority::class)],
            'attachments' => UploadPolicy::collectionRules('product_attachments'),
            'attachments.*' => UploadPolicy::fileRules('product_attachments', true),
            'attachments_upload_uuids' => ['nullable', 'array', 'max:3'],
            'attachments_upload_uuids.*' => UploadPolicy::tokenRules('product_attachments'),
        ];
    }

    public function messages(): array
    {
        return [
            'attachments.max' => 'Maksimal :max lampiran dalam satu pengiriman.',
            'attachments.*.max' => 'Ukuran setiap lampiran maksimal 10 MB.',
        ];
    }
}
