<?php

namespace App\Policies;

use App\Models\User;

class HariLiburPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('hari-libur.view');
    }

    public function create(User $user): bool
    {
        return $user->can('hari-libur.create');
    }

    public function update(User $user): bool
    {
        return $user->can('hari-libur.update');
    }

    public function delete(User $user): bool
    {
        return $user->can('hari-libur.delete');
    }
}
