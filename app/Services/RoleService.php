<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use App\Repositories\RoleRepository;
use App\Support\PerPage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class RoleService
{
    public function __construct(private RoleRepository $roleRepository) {}

    public function list(int $perPage = PerPage::DEFAULT): LengthAwarePaginator
    {
        return $this->roleRepository->paginate($perPage);
    }

    /**
     * @throws \DomainException Jika aktor bukan super_admin.
     */
    public function store(User $actor, array $data): Role
    {
        $this->ensureActorIsSuperAdmin($actor);

        return DB::transaction(function () use ($data) {
            $role = $this->roleRepository->create($data['name'], $data['koperasi_id']);
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
     * @throws \DomainException Jika role masih dipakai oleh pengguna, atau
     *                          aktor bukan super_admin.
     */
    public function destroy(User $actor, Role $role): void
    {
        $this->destroyMany($actor, [$role->id]);
    }

    /**
     * @throws \DomainException Jika role masih dipakai oleh pengguna, atau
     *                          aktor bukan super_admin.
     */
    public function destroyMany(User $actor, array $ids): int
    {
        $this->ensureActorIsSuperAdmin($actor);

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

    /**
     * Bikin/hapus role dikunci ke super_admin — bukan sekadar permission
     * biasa, supaya admin_primer tidak bisa membuka celah ini lewat toggle
     * permission pada role yang dia kelola sendiri.
     *
     * @throws \DomainException
     */
    private function ensureActorIsSuperAdmin(User $actor): void
    {
        if (! $actor->isSuperAdmin()) {
            throw new \DomainException('Hanya super admin yang dapat membuat atau menghapus role.');
        }
    }
}
