<?php

namespace App\Http\Requests\DokumenBarang;

use App\Support\UploadPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDokumenBarangRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('kelolaDokumen', $this->route('barang'));
    }

    public function rules(): array
    {
        return [
            'jenis_dokumen' => ['required', Rule::in(config('inventaris.jenis_dokumen'))],
            'dokumen' => UploadPolicy::fileOrTokenRules('business_documents', 'dokumen_upload_uuid', true),
            'dokumen_upload_uuid' => UploadPolicy::tokenRules('business_documents', true, 'dokumen'),
        ];
    }
}
