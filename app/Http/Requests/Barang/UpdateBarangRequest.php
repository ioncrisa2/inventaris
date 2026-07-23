<?php

namespace App\Http\Requests\Barang;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBarangRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nama_barang' => ['required', 'string', 'max:255'],
            'kategori' => ['required', Rule::in(config('inventaris.kategori'))],
            'unit_kerja_id' => ['required', Rule::exists('unit_kerja', 'id')],
            'tanggal_perolehan' => ['required', 'date', 'before_or_equal:today'],
            'harga_perolehan' => ['required', 'numeric', 'min:0'],
            'foto_sampul' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'dokumen' => ['nullable', 'array'],
            'dokumen.*.jenis_dokumen' => ['nullable', 'required_with:dokumen.*.dokumen', Rule::in(config('inventaris.jenis_dokumen'))],
            'dokumen.*.dokumen' => ['nullable', 'required_with:dokumen.*.jenis_dokumen', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];
    }
}
