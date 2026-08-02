<?php

namespace App\Http\Requests\Barang;

use App\Support\TenantRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBarangRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $jenisBarang = config('kategori_penyusutan.item_per_golongan')[$this->input('kategori')] ?? [];

        return [
            'nama_barang' => ['required', 'string', 'max:255'],
            'kategori' => ['required', Rule::in(config('inventaris.kategori'))],
            'jenis_barang' => ['required', 'string', 'max:255', Rule::in($jenisBarang)],
            'unit_kerja_id' => ['required', TenantRule::exists('unit_kerja')],
            'tanggal_perolehan' => ['required', 'date', 'before_or_equal:today'],
            'harga_perolehan' => ['required', 'numeric', 'min:0'],
            'foto_sampul' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'foto_pendukung' => ['nullable', 'array'],
            'foto_pendukung.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'dokumen' => ['nullable', 'array'],
            'dokumen.*.jenis_dokumen' => ['nullable', 'required_with:dokumen.*.dokumen', Rule::in(config('inventaris.jenis_dokumen'))],
            'dokumen.*.dokumen' => ['nullable', 'required_with:dokumen.*.jenis_dokumen', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'jenis_barang.in' => 'Jenis barang tidak sesuai dengan golongan penyusutan yang dipilih.',
        ];
    }
}
