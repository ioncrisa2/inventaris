<?php

namespace App\Http\Requests\Pengaturan;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateHariOperasionalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('pengaturan.hari-operasional.update');
    }

    public function rules(): array
    {
        return [
            'hari_operasional' => ['required', 'array', 'min:1'],
            'hari_operasional.*' => ['integer', Rule::in(range(1, 7))],
        ];
    }

    public function messages(): array
    {
        return [
            'hari_operasional.required' => 'Pilih minimal satu hari operasional.',
            'hari_operasional.min' => 'Pilih minimal satu hari operasional.',
        ];
    }
}
