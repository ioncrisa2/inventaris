<?php

namespace App\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;

class RoleRepository
{
    public function paginate(int $perPage = 10): LengthAwarePaginator
    {
        return Role::query()
            ->withCount(['users', 'permissions'])
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function create(string $name): Role
    {
        return Role::create(['name' => $name, 'guard_name' => 'web']);
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
