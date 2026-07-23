<?php

namespace App\Policies;

use App\Models\User;

class TransaksiGajiPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('transaksi-gaji.view');
    }

    public function view(User $user): bool
    {
        return $user->can('transaksi-gaji.view');
    }

    public function create(User $user): bool
    {
        return $user->can('transaksi-gaji.create');
    }

    public function update(User $user): bool
    {
        return $user->can('transaksi-gaji.update');
    }

    public function delete(User $user): bool
    {
        return $user->can('transaksi-gaji.delete');
    }
}
