<?php

namespace App\Http\Requests\Barang;

use App\Rules\ValidUploadFile;
use App\Support\TenantRule;
use App\Support\UploadPolicy;
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
            'lokasi_penempatan' => ['required', Rule::in(config('inventaris.lokasi_penempatan'))],
            'keterangan_lokasi' => ['nullable', 'string', 'max:255'],
            'tanggal_perolehan' => ['required', 'date', 'before_or_equal:today'],
            'harga_perolehan' => ['required', 'numeric', 'min:0'],
            'foto_sampul' => UploadPolicy::fileRules('asset_photo'),
            'foto_sampul_upload_uuid' => UploadPolicy::tokenRules('asset_photo'),
            'foto_pendukung' => UploadPolicy::collectionRules('asset_gallery'),
            'foto_pendukung.*' => UploadPolicy::fileRules('asset_gallery', true),
            'foto_pendukung_upload_uuids' => ['nullable', 'array', 'max:5'],
            'foto_pendukung_upload_uuids.*' => UploadPolicy::tokenRules('asset_gallery'),
            'dokumen' => UploadPolicy::collectionRules('business_documents'),
            'dokumen.*.jenis_dokumen' => ['nullable', 'required_with:dokumen.*.dokumen,dokumen.*.dokumen_upload_uuid', Rule::in(config('inventaris.jenis_dokumen'))],
            'dokumen.*.dokumen' => [
                'exclude_without:dokumen.*.jenis_dokumen',
                'required_without:dokumen.*.dokumen_upload_uuid',
                'file',
                new ValidUploadFile('business_documents'),
            ],
            'dokumen.*.dokumen_upload_uuid' => [
                'exclude_without:dokumen.*.jenis_dokumen',
                'required_without:dokumen.*.dokumen',
                'uuid',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'jenis_barang.in' => 'Jenis barang tidak sesuai dengan golongan penyusutan yang dipilih.',
        ];
    }
}
