<?php

namespace App\Http\Requests\Laporan;

use App\Support\TenantRule;
use Illuminate\Foundation\Http\FormRequest;

class AbsensiLaporanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return match ($this->route()->getName()) {
            'laporan.absensi.cetak' => $this->user()->can('laporan.absensi.cetak'),
            'laporan.absensi.export' => $this->user()->can('laporan.absensi.export'),
            default => $this->user()->can('laporan.absensi.view'),
        };
    }

    public function rules(): array
    {
        return [
            'karyawan_id' => ['nullable', TenantRule::exists('karyawan')],
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
