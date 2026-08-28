<?php

namespace App\Http\Requests\ProductRequest;

use App\Enums\ProductRequestPriority;
use App\Enums\ProductRequestStatus;
use App\Enums\ProductRequestType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OwnerProductRequestIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isSystemOwner();
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'koperasi_id' => ['nullable', 'integer', 'exists:koperasi,id'],
            'type' => ['nullable', Rule::enum(ProductRequestType::class)],
            'status' => ['nullable', Rule::enum(ProductRequestStatus::class)],
            'priority' => ['nullable', Rule::enum(ProductRequestPriority::class)],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'per_page' => ['nullable', 'integer', Rule::in([10, 25, 50, 100])],
        ];
    }
}
