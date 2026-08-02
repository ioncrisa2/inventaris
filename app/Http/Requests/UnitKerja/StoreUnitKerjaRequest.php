<?php

namespace App\Http\Requests\UnitKerja;

use App\Support\TenantRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreUnitKerjaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nama_unit' => ['required', 'string', 'max:255', TenantRule::unique('unit_kerja', 'nama_unit')],
            'kode' => ['nullable', 'string', 'max:10'],
        ];
    }
}
