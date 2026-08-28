<?php

namespace App\Http\Requests\Owner;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMaintenanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSystemOwner() ?? false;
    }

    public function rules(): array
    {
        return [
            'message' => ['nullable', 'string', 'max:1000'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (blank($this->input('starts_at')) && filled($this->input('ends_at'))) {
            $this->merge(['starts_at' => now()->toDateTimeString()]);
        }
    }
}
