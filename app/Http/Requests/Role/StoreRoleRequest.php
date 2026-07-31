<?php

namespace App\Http\Requests\Role;

use App\Support\PermissionCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('role.create') && $this->user()->isSuperAdmin();
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['permissions' => $this->input('permissions', [])]);
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('roles', 'name')
                    ->where('koperasi_id', $this->koperasiIdForUniqueCheck())
                    ->ignore($this->roleId()),
            ],
            'koperasi_id' => ['required', Rule::exists('koperasi', 'id')],
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::in(PermissionCatalog::all())],
        ];
    }

    protected function roleId(): ?int
    {
        return null;
    }

    /**
     * Role boleh punya nama sama persis di koperasi berbeda (unique
     * constraint-nya composite koperasi_id+name), jadi cek unik nama harus
     * diikat ke koperasi yang sedang dituju, bukan global.
     */
    protected function koperasiIdForUniqueCheck(): ?int
    {
        return $this->filled('koperasi_id') ? (int) $this->input('koperasi_id') : null;
    }
}
