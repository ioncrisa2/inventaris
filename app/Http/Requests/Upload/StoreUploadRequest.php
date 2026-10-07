<?php

namespace App\Http\Requests\Upload;

use App\Services\StorageUsageService;
use App\Support\UploadPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

class StoreUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->koperasi_id !== null || (bool) $this->user()?->isSystemOwner();
    }

    public function rules(): array
    {
        $policies = array_keys((array) config('uploads.policies', []));
        $requested = (string) $this->input('policy');
        $policy = in_array($requested, $policies, true) ? $requested : ($policies[0] ?? 'employee_photo');

        return [
            'policy' => ['required', 'string', Rule::in($policies)],
            'file' => UploadPolicy::fileRules($policy, true),
            'koperasi_id' => [
                Rule::requiredIf(fn (): bool => $this->user()?->koperasi_id === null),
                Rule::prohibitedIf(fn (): bool => $this->user()?->koperasi_id !== null),
                'nullable',
                'integer',
                Rule::exists('koperasi', 'id'),
            ],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if (! $this->hasFile('file') || ! $this->file('file')->isValid()) {
                    return;
                }

                $koperasiId = $this->user()?->koperasi_id ?? (int) $this->input('koperasi_id');
                if (! $koperasiId) {
                    return;
                }

                $storageService = app(StorageUsageService::class);
                $currentUsage = $storageService->tenantUsage($koperasiId);
                $newFileSize = $this->file('file')->getSize();

                if (($currentUsage + $newFileSize) > StorageUsageService::TENANT_QUOTA_BYTES) {
                    $validator->errors()->add(
                        'file',
                        'Gagal mengunggah. Kuota penyimpanan 1 GB untuk koperasi ini sudah habis.'
                    );
                }
            }
        ];
    }
}
