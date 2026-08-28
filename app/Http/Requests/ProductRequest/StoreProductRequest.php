<?php

namespace App\Http\Requests\ProductRequest;

use App\Enums\ProductRequestPriority;
use App\Enums\ProductRequestType;
use App\Models\ProductRequest;
use App\Rules\ValidProductRequestAttachment;
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
        $attachments = config('product_requests.attachments');

        return [
            'type' => ['required', Rule::enum(ProductRequestType::class)],
            'module' => ['nullable', Rule::in(array_keys(config('product_requests.modules')))],
            'title' => ['required', 'string', 'min:5', 'max:180'],
            'description' => ['required', 'string', 'min:10', 'max:10000'],
            'requester_priority' => ['required', Rule::enum(ProductRequestPriority::class)],
            'attachments' => ['nullable', 'array', 'max:'.$attachments['max_files_per_submission']],
            'attachments.*' => [
                'file',
                new ValidProductRequestAttachment,
                'max:'.$attachments['max_file_kilobytes'],
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'attachments.max' => 'Maksimal :max lampiran dalam satu pengiriman.',
            'attachments.*.max' => 'Ukuran setiap lampiran maksimal 5 MB.',
        ];
    }
}
