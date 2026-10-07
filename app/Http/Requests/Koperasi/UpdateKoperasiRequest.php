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
            'feature_overrides' => ['nullable', 'array'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $overrides = $this->input('feature_overrides', []);
        if (is_array($overrides)) {
            $filtered = [];
            foreach ($overrides as $k => $v) {
                if ($v === '1') {
                    $filtered[$k] = true;
                } elseif ($v === '0') {
                    $filtered[$k] = false;
                }
            }
            $this->merge(['feature_overrides' => $filtered]);
        }

        $this->merge([
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
