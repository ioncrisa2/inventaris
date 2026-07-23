<?php

namespace App\Http\Requests\Laporan;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InventarisLaporanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('laporan.inventaris.view');
    }

    public function rules(): array
    {
        return [
            'unit_kerja_id' => ['nullable', Rule::exists('unit_kerja', 'id')],
            'kategori' => ['nullable', Rule::in(config('inventaris.kategori'))],
            'tanggal_awal' => ['nullable', 'date'],
            'tanggal_akhir' => ['nullable', 'date', 'after_or_equal:tanggal_awal'],
        ];
    }
}
