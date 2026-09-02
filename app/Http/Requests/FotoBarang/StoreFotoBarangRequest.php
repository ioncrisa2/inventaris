<?php

namespace App\Http\Requests\FotoBarang;

use App\Support\UploadPolicy;
use Illuminate\Foundation\Http\FormRequest;

class StoreFotoBarangRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('kelolaFoto', $this->route('barang'));
    }

    public function rules(): array
    {
        return [
            'foto' => UploadPolicy::fileOrTokenRules('asset_photo', 'foto_upload_uuid', true),
            'foto_upload_uuid' => UploadPolicy::tokenRules('asset_photo', true, 'foto'),
            'keterangan' => ['nullable', 'string', 'max:255'],
        ];
    }
}
