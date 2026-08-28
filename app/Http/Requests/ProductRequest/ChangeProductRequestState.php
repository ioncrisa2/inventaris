<?php

namespace App\Http\Requests\ProductRequest;

use Illuminate\Foundation\Http\FormRequest;

class ChangeProductRequestState extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('product-request.close');
    }

    public function rules(): array
    {
        return ['reason' => ['nullable', 'string', 'max:500']];
    }
}
