<?php

namespace App\Repositories;

use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class RoleRepository
{
    /**
     * Super Admin melihat seluruh role. Aktor tenant hanya melihat role yang
     * koperasi_id-nya sama persis dengan koperasi miliknya; role global dan
     * role tenant lain tidak pernah masuk ke hasil query.
     */
    public function paginateFor(User $actor, int $perPage = 10): LengthAwarePaginator
    {
        $query = Role::query();

        if (! $actor->isSuperAdmin()) {
            $actor->koperasi_id === null
                ? $query->whereRaw('1 = 0')
                : $query->where('koperasi_id', (int) $actor->koperasi_id);
        }

        return $query
            ->with('koperasi:id,nama')
            ->withCount(['users', 'permissions'])
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * koperasi_id diisi lewat set properti langsung (bukan mass-assignment)
     * karena Role sengaja tidak pakai trait BelongsToKoperasi (lihat
     * App\Models\Role) — tidak ada auto-assign yang bisa diandalkan.
     */
    public function create(string $name, ?int $koperasiId): Role
    {
        $role = new Role(['name' => $name, 'guard_name' => 'web']);
        $role->koperasi_id = $koperasiId;
        $role->save();

        return $role;
    }

    public function findManyForDelete(array $ids): Collection
    {
        return Role::query()
            ->withExists('users')
            ->whereKey($ids)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    public function update(Role $role, string $name): Role
    {
        $role->update(['name' => $name]);

        return $role;
    }

    public function delete(Role $role): void
    {
        $role->delete();
    }
}
