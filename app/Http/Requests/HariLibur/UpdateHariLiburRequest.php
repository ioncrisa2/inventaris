<?php

namespace App\Http\Requests\HariLibur;

use App\Support\TenantRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateHariLiburRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tanggal' => [
                'required',
                'date',
                TenantRule::uniqueFor(
                    'hari_libur',
                    'tanggal',
                    $this->route('hari_libur')?->koperasi_id,
                )->ignore($this->route('hari_libur')),
            ],
            'keterangan' => ['required', 'string', 'max:255'],
        ];
    }
}
