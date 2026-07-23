<?php

namespace App\Services;

use App\Repositories\RoleRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class RoleService
{
    public function __construct(private RoleRepository $roleRepository) {}

    public function list(): LengthAwarePaginator
    {
        return $this->roleRepository->paginate();
    }

    public function store(array $data): Role
    {
        return DB::transaction(function () use ($data) {
            $role = $this->roleRepository->create($data['name']);
            $role->syncPermissions($data['permissions']);

            return $role;
        }, 3);
    }

    public function update(Role $role, array $data): Role
    {
        return DB::transaction(function () use ($role, $data) {
            $this->roleRepository->update($role, $data['name']);
            $role->syncPermissions($data['permissions']);

            return $role;
        }, 3);
    }

    /**
     * @throws \DomainException Jika role masih dipakai oleh pengguna.
     */
    public function destroy(Role $role): void
    {
        $this->destroyMany([$role->id]);
    }

    public function destroyMany(array $ids): int
    {
        return DB::transaction(function () use ($ids) {
            $ids = array_values(array_unique(array_map('intval', $ids)));
            $roles = $this->roleRepository->findManyForDelete($ids);

            if ($ids === [] || $roles->count() !== count($ids)) {
                throw new \DomainException('Sebagian role sudah tidak tersedia. Muat ulang halaman lalu coba lagi.');
            }

            $roles->each(function (Role $role) {
                $atribut = $role->getAttributes();
                if ((bool) $atribut['users_exists']) {
                    throw new \DomainException('Role tidak dapat dihapus karena masih dipakai oleh pengguna. Pindahkan pengguna ke role lain terlebih dahulu.');
                }

                $this->roleRepository->delete($role);
            });

            return $roles->count();
        }, 3);
    }
}
