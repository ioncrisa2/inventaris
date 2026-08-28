<?php

namespace App\Policies;

use App\Models\Karyawan;
use App\Models\User;

class KaryawanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('karyawan.view');
    }

    public function view(User $user): bool
    {
        return $user->can('karyawan.view');
    }

    public function create(User $user): bool
    {
        return $user->can('karyawan.create');
    }

    public function update(User $user): bool
    {
        return $user->can('karyawan.update');
    }

    public function updateEmployment(User $user): bool
    {
        return $user->can('karyawan.kepegawaian.update');
    }

    public function viewSalary(User $user): bool
    {
        return $user->can('karyawan.gaji.view') || $user->can('karyawan.gaji.update');
    }

    public function updateSalary(User $user): bool
    {
        return $user->can('karyawan.gaji.update');
    }

    public function viewHistory(User $user): bool
    {
        return $user->can('karyawan.riwayat.view');
    }

    public function delete(User $user): bool
    {
        return $user->can('karyawan.delete');
    }

    public function manageAccount(User $user, Karyawan $karyawan): bool
    {
        return $user->can('karyawan.akun.update')
            && $user->koperasi_id !== null
            && (int) $user->koperasi_id === (int) $karyawan->koperasi_id;
    }
}
