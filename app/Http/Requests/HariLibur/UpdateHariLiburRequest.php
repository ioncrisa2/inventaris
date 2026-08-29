<?php

namespace App\Http\Requests\HariLibur;

use App\Support\CurrentTenant;
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
                Rule::unique('hari_libur', 'tanggal')
                    ->where(fn ($query) => $query->whereIn('cakupan_id', [0, CurrentTenant::id() ?? -1]))
                    ->ignore($this->route('hari_libur')),
            ],
            'keterangan' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'tanggal.unique' => 'Tanggal tersebut sudah tercatat sebagai baseline nasional atau hari libur tambahan.',
        ];
    }
}
