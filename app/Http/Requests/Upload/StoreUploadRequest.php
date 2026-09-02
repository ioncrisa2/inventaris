<?php

namespace App\Http\Requests\Upload;

use App\Support\UploadPolicy;
use Illuminate\Foundation\Http\FormRequest;
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
}
