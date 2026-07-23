<?php

namespace App\Http\Requests\Laporan;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AbsensiLaporanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('laporan.absensi.view');
    }

    public function rules(): array
    {
        return [
            'karyawan_id' => ['nullable', Rule::exists('karyawan', 'id')],
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
