<?php

namespace App\Http\Requests\Barang;

use Illuminate\Foundation\Http\FormRequest;

class CetakBarcodeMassalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('barang.view');
    }

    public function rules(): array
    {
        return [
            'barang_ids' => ['required', 'array', 'min:1', 'max:100'],
            'barang_ids.*' => ['required', 'integer', 'distinct', 'exists:barang,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'barang_ids.required' => 'Pilih minimal satu barang untuk mencetak barcode.',
            'barang_ids.min' => 'Pilih minimal satu barang untuk mencetak barcode.',
            'barang_ids.max' => 'Maksimal 100 barcode dapat dicetak sekaligus.',
            'barang_ids.*.distinct' => 'Pilihan barang tidak boleh duplikat.',
            'barang_ids.*.exists' => 'Salah satu barang yang dipilih sudah tidak tersedia.',
        ];
    }
}
