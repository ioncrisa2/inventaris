<?php

namespace App\Http\Requests\UnitKerja;

use App\Support\TenantRule;
use Illuminate\Foundation\Http\FormRequest;

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
                TenantRule::uniqueFor(
                    'unit_kerja',
                    'nama_unit',
                    $this->route('unit_kerja')?->koperasi_id,
                )->ignore($this->route('unit_kerja')),
            ],
            'kode' => ['nullable', 'string', 'max:10'],
        ];
    }
}
