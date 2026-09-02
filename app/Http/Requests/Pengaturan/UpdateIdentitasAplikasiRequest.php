<?php

namespace App\Http\Requests\Pengaturan;

use App\Support\UploadPolicy;
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
            'logo' => UploadPolicy::fileRules('logo'),
            'logo_upload_uuid' => UploadPolicy::tokenRules('logo'),
        ];
    }
}
