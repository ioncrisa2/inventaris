<?php

namespace App\Policies;

use App\Models\User;

class BarangPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('barang.view');
    }

    public function view(User $user): bool
    {
        return $user->can('barang.view');
    }

    public function create(User $user): bool
    {
        return $user->can('barang.create');
    }

    public function update(User $user): bool
    {
        return $user->can('barang.update');
    }

    public function delete(User $user): bool
    {
        return $user->can('barang.delete');
    }
}
