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

    public function update(User $user): bool
    {
        return $user->can('pengguna.update');
    }

    public function delete(User $user): bool
    {
        return $user->can('pengguna.delete');
    }
}
