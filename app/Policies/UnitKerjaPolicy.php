<?php

namespace App\Policies;

use App\Models\User;

class UnitKerjaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('unit-kerja.view');
    }

    public function create(User $user): bool
    {
        return $user->can('unit-kerja.create');
    }

    public function update(User $user): bool
    {
        return $user->can('unit-kerja.update');
    }

    public function delete(User $user): bool
    {
        return $user->can('unit-kerja.delete');
    }
}
