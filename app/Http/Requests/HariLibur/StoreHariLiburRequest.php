<?php

namespace App\Http\Requests\HariLibur;

use App\Support\TenantRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreHariLiburRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tanggal' => ['required', 'date', TenantRule::unique('hari_libur', 'tanggal')],
            'keterangan' => ['required', 'string', 'max:255'],
        ];
    }
}
