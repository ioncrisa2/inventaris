<?php

namespace App\Http\Requests\Role;

use Illuminate\Validation\Rule;

class UpdateRoleRequest extends StoreRoleRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $role = $this->route('role');

        if ($this->routeIs('owner.roles.update')) {
            return $user?->isSystemOwner() === true
                && $role !== null
                && $role->name !== 'system_owner';
        }

        if (! $user->can('role.update') || $role === null) {
            return false;
        }

        if ($user->isSuperAdmin() && $role->isAdminPrimerRole()) {
            return true;
        }

        return ! $role->isSystem()
            && ($user->isSuperAdmin() || (int) $role->koperasi_id === (int) $user->koperasi_id);
    }

    protected function roleId(): ?int
    {
        return $this->route('role')?->id;
    }

    /**
     * Update tidak boleh memindah role ke koperasi lain — dikunci ke
     * koperasi_id role yang sudah ada, bukan dari input form (yang memang
     * tidak dikirim sama sekali saat edit, lihat role/form.blade.php).
     */
    protected function koperasiIdForUniqueCheck(): ?int
    {
        return $this->route('role')?->koperasi_id;
    }

    public function rules(): array
    {
        $rules = collect(parent::rules())->except('koperasi_id')->all();
        $role = $this->route('role');

        $isPermissionOnlySystemUpdate = $role?->isSystem()
            && ($this->routeIs('owner.roles.update')
                || ($this->user()?->isSuperAdmin() && $role->isAdminPrimerRole()));

        if ($isPermissionOnlySystemUpdate) {
            $rules['name'] = ['required', 'string', Rule::in([$role->name])];
        }

        return $rules;
    }
}
