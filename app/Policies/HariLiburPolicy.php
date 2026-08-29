<?php

namespace App\Policies;

use App\Models\HariLibur;
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

    public function update(User $user, ?HariLibur $hariLibur = null): bool
    {
        return $user->can('hari-libur.update')
            && ($hariLibur === null
                || ($hariLibur->koperasi_id !== null
                    && (int) $hariLibur->koperasi_id === (int) $user->koperasi_id));
    }

    public function delete(User $user, ?HariLibur $hariLibur = null): bool
    {
        return $user->can('hari-libur.delete')
            && ($hariLibur === null
                || ($hariLibur->koperasi_id !== null
                    && (int) $hariLibur->koperasi_id === (int) $user->koperasi_id));
    }
}
