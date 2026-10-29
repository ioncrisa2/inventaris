<?php

namespace App\Http\Requests\Laporan;

use App\Support\TenantRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'koperasi_id' => ['nullable', 'integer', Rule::exists('koperasi', 'id'), Rule::prohibitedIf(! $this->user()->isSuperAdmin())],
            'unit_kerja_id' => ['nullable', TenantRule::exists('unit_kerja')],
            'karyawan_id' => ['nullable', TenantRule::exists('karyawan')],
            'bulan' => ['nullable', 'integer', 'between:1,12'],
            'tahun' => ['nullable', 'integer', 'digits:4'],
            'tipe_periode' => ['nullable', Rule::in(['kalender', 'penggajian'])],
            'status' => ['nullable', 'string', 'max:50'],
            'mode' => ['nullable', Rule::in(['karyawan', 'tanggal'])],
            'search' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', 'string', 'max:50'],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer'],
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
