<?php

namespace App\Policies;

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
}
