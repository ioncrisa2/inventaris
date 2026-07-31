<?php

namespace App\Http\Requests\Koperasi;

use Illuminate\Foundation\Http\FormRequest;

class UpdateKoperasiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isSuperAdmin();
    }

    public function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'max:255'],
            'expires_at' => ['nullable', 'date'],
            'is_active' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active')]);
    }
}
