<?php

namespace App\Http\Requests\HariLibur;

use Illuminate\Foundation\Http\FormRequest;

class BandingkanHariLiburRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() === true;
    }

    public function rules(): array
    {
        return [
            'tahun' => ['nullable', 'integer', 'between:2000,2100'],
        ];
    }
}
