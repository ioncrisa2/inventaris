<?php

namespace App\Http\Requests\HariLibur;

use App\Models\HariLibur;
use App\Support\UploadPolicy;
use Illuminate\Foundation\Http\FormRequest;

class ImportHariLiburRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', HariLibur::class);
    }

    public function rules(): array
    {
        return [
            'file' => UploadPolicy::fileOrTokenRules('calendar_import', 'file_upload_uuid', true),
            'file_upload_uuid' => UploadPolicy::tokenRules('calendar_import', true, 'file'),
        ];
    }
}
