<?php

namespace App\Http\Requests\Laporan;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PenyusutanLaporanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('laporan.penyusutan.view');
    }

    public function rules(): array
    {
        return [
            'unit_kerja_id' => ['nullable', Rule::exists('unit_kerja', 'id')],
            'kategori' => ['nullable', Rule::in(config('inventaris.kategori'))],
            'tahun' => ['nullable', 'integer', 'digits:4'],
        ];
    }

    public function tahun(): int
    {
        return (int) $this->input('tahun', now()->year);
    }
}
