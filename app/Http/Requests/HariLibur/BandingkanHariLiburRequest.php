<?php

namespace App\Http\Requests\HariLibur;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BandingkanHariLiburRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() === true;
    }

    public function rules(): array
    {
        return [
            'tahun' => ['nullable', 'required_with:koperasi_id', 'integer', 'between:2000,2100'],
            'koperasi_id' => ['nullable', 'required_with:tahun', 'integer', Rule::exists('koperasi', 'id')],
        ];
    }

    public function messages(): array
    {
        return [
            'koperasi_id.exists' => 'Koperasi tujuan tidak tersedia.',
        ];
    }
}
