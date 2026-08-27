<?php

namespace App\Http\Requests\Role;

use App\Models\Role as AppRole;
use App\Support\PermissionCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user->can('role.create')
            && ($user->isSuperAdmin() || $user->isAdminPrimer());
    }

    protected function prepareForValidation(): void
    {
        $data = ['permissions' => $this->input('permissions', [])];

        // Tenant tujuan admin primer selalu berasal dari sesi login. Nilai
        // koperasi_id yang mungkin dipalsukan di request sengaja ditimpa.
        if ($this->user()?->isAdminPrimer()) {
            $data['koperasi_id'] = $this->user()->koperasi_id;
        }

        $this->merge($data);
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:255', Rule::notIn(AppRole::SYSTEM_NAMES),
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
