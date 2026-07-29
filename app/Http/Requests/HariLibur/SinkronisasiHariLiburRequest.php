<?php

namespace App\Http\Requests\HariLibur;

use Illuminate\Foundation\Http\FormRequest;

class SinkronisasiHariLiburRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tahun' => ['required', 'integer', 'min:2000', 'max:2100'],
            'pilihan' => ['nullable', 'array'],
            'pilihan.*' => ['date'],
        ];
    }
}
