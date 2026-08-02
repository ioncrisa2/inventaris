<?php

namespace App\Http\Requests\Laporan;

use App\Models\Karyawan;
use App\Support\TenantRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class KepegawaianLaporanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return match ($this->route()->getName()) {
            'laporan.kepegawaian.cetak' => $this->user()->can('laporan.kepegawaian.cetak'),
            'laporan.kepegawaian.export' => $this->user()->can('laporan.kepegawaian.export'),
            default => $this->user()->can('laporan.kepegawaian.view'),
        };
    }

    public function rules(): array
    {
        return [
            'unit_kerja_id' => ['nullable', TenantRule::exists('unit_kerja')],
            'status_karyawan' => ['nullable', Rule::in(Karyawan::STATUSES)],
        ];
    }
}
