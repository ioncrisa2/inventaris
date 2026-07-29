<?php

namespace App\Http\Requests\HariLibur;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
                Rule::unique('hari_libur', 'tanggal')->ignore($this->route('hari_libur')),
            ],
            'keterangan' => ['required', 'string', 'max:255'],
        ];
    }
}
