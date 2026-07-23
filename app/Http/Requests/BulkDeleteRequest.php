<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkDeleteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['required', 'integer', 'distinct'],
        ];
    }

    public function messages(): array
    {
        return [
            'ids.required' => 'Pilih minimal satu data untuk dihapus.',
            'ids.min' => 'Pilih minimal satu data untuk dihapus.',
            'ids.max' => 'Maksimal 100 data dapat dihapus sekaligus.',
            'ids.*.integer' => 'Pilihan data tidak valid.',
            'ids.*.distinct' => 'Pilihan data tidak boleh duplikat.',
        ];
    }
}
