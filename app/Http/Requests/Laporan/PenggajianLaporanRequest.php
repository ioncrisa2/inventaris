<?php

namespace App\Http\Requests\Laporan;

use App\Support\TenantRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PenggajianLaporanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return match ($this->route()->getName()) {
            'laporan.penggajian.cetak' => $this->user()->can('laporan.penggajian.cetak'),
            'laporan.penggajian.export' => $this->user()->can('laporan.penggajian.export'),
            default => $this->user()->can('laporan.penggajian.view'),
        };
    }

    public function rules(): array
    {
        return [
            'koperasi_id' => ['nullable', 'integer', Rule::exists('koperasi', 'id'), Rule::prohibitedIf(! $this->user()->isSuperAdmin())],
            'unit_kerja_id' => ['nullable', TenantRule::exists('unit_kerja')],
            'bulan' => ['nullable', 'integer', 'between:1,12'],
            'tahun' => ['nullable', 'integer', 'digits:4'],
        ];
    }

    public function bulan(): int
    {
        return (int) $this->input('bulan', now()->month);
    }

    public function tahun(): int
    {
        return (int) $this->input('tahun', now()->year);
    }
}
