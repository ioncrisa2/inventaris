<?php

namespace App\Http\Requests\FotoBarang;

use Illuminate\Foundation\Http\FormRequest;

class StoreFotoBarangRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('barang'));
    }

    public function rules(): array
    {
        return [
            'foto' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'keterangan' => ['nullable', 'string', 'max:255'],
        ];
    }
}
