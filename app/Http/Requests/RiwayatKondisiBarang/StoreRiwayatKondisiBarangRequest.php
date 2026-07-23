<?php

namespace App\Http\Requests\RiwayatKondisiBarang;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRiwayatKondisiBarangRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('barang'));
    }

    public function rules(): array
    {
        return [
            'tanggal_pemeriksaan' => [
                'required',
                'date',
                'before_or_equal:today',
                'after_or_equal:'.$this->route('barang')->tanggal_perolehan->format('Y-m-d'),
            ],
            'kondisi' => ['required', Rule::in(config('inventaris.kondisi'))],
            'keterangan' => ['nullable', 'string', 'max:1000'],
            'biaya_perbaikan' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
