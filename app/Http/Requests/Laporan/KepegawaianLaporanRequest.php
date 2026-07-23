<?php

namespace App\Http\Requests\Laporan;

use App\Models\Karyawan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class KepegawaianLaporanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('laporan.kepegawaian.view');
    }

    public function rules(): array
    {
        return [
            'unit_kerja_id' => ['nullable', Rule::exists('unit_kerja', 'id')],
            'status_karyawan' => ['nullable', Rule::in(Karyawan::STATUSES)],
        ];
    }
}
