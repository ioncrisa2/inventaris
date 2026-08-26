<?php

namespace App\Http\Requests\Profile;

use App\Support\TenantRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    protected $errorBag = 'updateProfile';

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'current_password'],
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($this->user()->id),
            ],
            'unit_kerja_id' => ['nullable', TenantRule::exists('unit_kerja')],
        ];
    }

    public function messages(): array
    {
        return [
            'current_password.required' => 'Masukkan password saat ini untuk menyimpan perubahan profil.',
            'current_password.current_password' => 'Password saat ini tidak sesuai.',
        ];
    }
}
