<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('pengguna.view');
    }

    public function create(User $user): bool
    {
        return $user->can('pengguna.create');
    }

    /**
     * $model nullable: bulkDestroy() mengotorisasi di level kelas dulu
     * (belum tahu target barisnya) — cek per-baris sesungguhnya terjadi di
     * UserService::ensureCanManage() saat masing-masing baris dieksekusi.
     */
    public function update(User $user, ?User $model = null): bool
    {
        return $user->can('pengguna.update') && (! $model || $this->canManage($user, $model));
    }

    public function delete(User $user, ?User $model = null): bool
    {
        return $user->can('pengguna.delete') && (! $model || $this->canManage($user, $model));
    }

    /**
     * Non-super-admin tidak boleh mengelola akun super_admin/admin_primer
     * (termasuk akunnya sendiri lewat halaman ini — profil sendiri diubah
     * lewat /profile, bukan /pengguna), atau akun koperasi lain. Permission
     * checkbox saja tidak cukup karena listing/route model binding tidak
     * otomatis discope ke koperasi aktor — lihat App\Models\User & UserRepository.
     */
    private function canManage(User $user, User $model): bool
    {
        if ($user->isSystemOwner() || $model->isSystemOwner()) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($model->isSuperAdmin() || $model->isAdminPrimer()) {
            return false;
        }

        return $model->koperasi_id === $user->koperasi_id;
    }
}
