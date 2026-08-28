<?php

namespace App\Policies;

use App\Models\TransaksiGaji;
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

    public function update(User $user, ?TransaksiGaji $transaksiGaji = null): bool
    {
        return $user->can('transaksi-gaji.update')
            && (! $transaksiGaji || ! $transaksiGaji->isPublished());
    }

    public function delete(User $user, ?TransaksiGaji $transaksiGaji = null): bool
    {
        return $user->can('transaksi-gaji.delete')
            && (! $transaksiGaji || ! $transaksiGaji->isPublished());
    }

    public function cetak(User $user): bool
    {
        return $user->can('transaksi-gaji.cetak');
    }

    public function publish(User $user, TransaksiGaji $transaksiGaji): bool
    {
        return $user->can('transaksi-gaji.publish') && ! $transaksiGaji->isPublished();
    }
}
