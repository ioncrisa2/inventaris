<?php

namespace App\Http\Requests\HariLibur;

use Illuminate\Foundation\Http\FormRequest;

class SinkronisasiHariLiburRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() === true;
    }

    public function rules(): array
    {
        return [
            'tahun' => ['required', 'integer', 'between:2000,2100'],
            'snapshot' => ['required', 'string', 'size:64', 'regex:/\A[a-f0-9]{64}\z/'],
            'pilihan' => ['nullable', 'array', 'max:366'],
            'pilihan.*' => ['required', 'date_format:Y-m-d', 'distinct'],
        ];
    }

    public function messages(): array
    {
        return [
            'pilihan.max' => 'Jumlah tanggal yang dipilih tidak wajar.',
        ];
    }
}
