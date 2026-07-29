<?php

namespace App\Http\Requests\HariLibur;

use Illuminate\Foundation\Http\FormRequest;

class BandingkanHariLiburRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tahun' => ['nullable', 'integer', 'min:2000', 'max:2100'],
        ];
    }
}
