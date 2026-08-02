<?php

namespace App\Http\Requests\User;

use App\Support\TenantRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->route('pengguna'))],
            'password' => ['nullable', Password::defaults()],
            'unit_kerja_id' => ['nullable', TenantRule::exists('unit_kerja')],
            'role_id' => ['required', 'integer', TenantRule::exists('roles')],
        ];
    }
}
