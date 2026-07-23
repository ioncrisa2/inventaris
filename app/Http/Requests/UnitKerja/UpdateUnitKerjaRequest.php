<?php

namespace App\Http\Requests\UnitKerja;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUnitKerjaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nama_unit' => [
                'required',
                'string',
                'max:255',
                Rule::unique('unit_kerja', 'nama_unit')->ignore($this->route('unit_kerja')),
            ],
            'kode' => ['nullable', 'string', 'max:10'],
        ];
    }
}
