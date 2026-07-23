<?php

namespace App\Http\Requests\DokumenBarang;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDokumenBarangRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('barang'));
    }

    public function rules(): array
    {
        return [
            'jenis_dokumen' => ['required', Rule::in(config('inventaris.jenis_dokumen'))],
            'dokumen' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];
    }
}
