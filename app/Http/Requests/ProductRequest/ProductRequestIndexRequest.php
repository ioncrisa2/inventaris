<?php

namespace App\Http\Requests\ProductRequest;

use App\Enums\ProductRequestStatus;
use App\Enums\ProductRequestType;
use App\Models\ProductRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequestIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('viewAny', ProductRequest::class);
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', Rule::enum(ProductRequestType::class)],
            'status' => ['nullable', Rule::enum(ProductRequestStatus::class)],
            'per_page' => ['nullable', 'integer', Rule::in([10, 25, 50, 100])],
        ];
    }
}
