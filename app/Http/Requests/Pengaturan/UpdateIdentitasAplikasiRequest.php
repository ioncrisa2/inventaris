<?php

namespace App\Http\Requests\Pengaturan;

use Illuminate\Foundation\Http\FormRequest;

class UpdateIdentitasAplikasiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('pengaturan.identitas.update');
    }

    public function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'max:255'],
            'alamat' => ['nullable', 'string', 'max:500'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }
}
