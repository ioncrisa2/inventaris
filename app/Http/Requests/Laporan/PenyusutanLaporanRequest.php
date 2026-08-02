<?php

namespace App\Http\Requests\Laporan;

use App\Support\TenantRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PenyusutanLaporanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return match ($this->route()->getName()) {
            'laporan.penyusutan.cetak' => $this->user()->can('laporan.penyusutan.cetak'),
            'laporan.penyusutan.export' => $this->user()->can('laporan.penyusutan.export'),
            default => $this->user()->can('laporan.penyusutan.view'),
        };
    }

    public function rules(): array
    {
        return [
            'unit_kerja_id' => ['nullable', TenantRule::exists('unit_kerja')],
            'kategori' => ['nullable', Rule::in(config('inventaris.kategori'))],
            'tahun' => ['nullable', 'integer', 'digits:4'],
        ];
    }

    public function tahun(): int
    {
        return (int) $this->input('tahun', now()->year);
    }
}
