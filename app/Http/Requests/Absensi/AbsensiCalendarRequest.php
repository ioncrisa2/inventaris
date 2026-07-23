<?php

namespace App\Http\Requests\Absensi;

use Illuminate\Foundation\Http\FormRequest;

class AbsensiCalendarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('absensi.view');
    }

    public function rules(): array
    {
        return [
            'bulan' => ['nullable', 'integer', 'between:1,12'],
            'tahun' => ['nullable', 'integer', 'digits:4'],
        ];
    }
}
