<?php

namespace App\Http\Requests\ProductRequest;

use App\Enums\ProductRequestPriority;
use App\Enums\ProductRequestStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOwnerProductRequestTriage extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isSystemOwner();
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(ProductRequestStatus::class)],
            'internal_priority' => ['nullable', Rule::enum(ProductRequestPriority::class)],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'duplicate_ticket' => ['nullable', 'string', 'max:32'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
