<?php

namespace App\Policies;

use App\Models\User;

class KomponenGajiPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('komponen-gaji.view');
    }

    public function create(User $user): bool
    {
        return $user->can('komponen-gaji.create');
    }

    public function update(User $user): bool
    {
        return $user->can('komponen-gaji.update');
    }

    public function delete(User $user): bool
    {
        return $user->can('komponen-gaji.delete');
    }
}
