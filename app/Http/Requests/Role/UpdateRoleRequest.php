<?php

namespace App\Http\Requests\Role;

class UpdateRoleRequest extends StoreRoleRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $role = $this->route('role');

        return $user->can('role.update')
            && $role !== null
            && ! $role->isSystem()
            && ($user->isSuperAdmin() || $role->koperasi_id === $user->koperasi_id);
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
        return collect(parent::rules())->except('koperasi_id')->all();
    }
}
