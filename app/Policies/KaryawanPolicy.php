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

    public function delete(User $user): bool
    {
        return $user->can('karyawan.delete');
    }
}
