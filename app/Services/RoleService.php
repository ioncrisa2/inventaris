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

    public function list(User $actor, int $perPage = PerPage::DEFAULT): LengthAwarePaginator
    {
        return $this->roleRepository->paginateFor($actor, $perPage);
    }

    /**
     * @throws \DomainException Jika aktor bukan super_admin/admin_primer.
     */
    public function store(User $actor, array $data): Role
    {
        $koperasiId = $this->resolveKoperasiIdForCreate(
            $actor,
            isset($data['koperasi_id']) ? (int) $data['koperasi_id'] : null,
        );

        return DB::transaction(function () use ($data, $koperasiId) {
            $role = $this->roleRepository->create($data['name'], $koperasiId);
            $role->syncPermissions($data['permissions']);

            return $role;
        }, 3);
    }

    /**
     * Super admin memilih koperasi tujuan. Untuk admin primer, tenant tujuan
     * selalu diambil dari identitas aktor agar pemanggilan service secara
     * langsung pun tidak dapat membuat role untuk koperasi lain.
     */
    private function resolveKoperasiIdForCreate(User $actor, ?int $requestedKoperasiId): int
    {
        if ($actor->isSuperAdmin()) {
            if ($requestedKoperasiId === null) {
                throw new \DomainException('Koperasi tujuan wajib dipilih.');
            }

            return $requestedKoperasiId;
        }

        if ($actor->isAdminPrimer() && $actor->koperasi_id !== null) {
            return (int) $actor->koperasi_id;
        }

        throw new \DomainException('Hanya super admin atau admin primer yang dapat membuat role.');
    }

    public function update(User $actor, Role $role, array $data): Role
    {
        $this->ensureActorCanUpdate($actor, $role);

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
                if ($role->isSystem()) {
                    throw new \DomainException('Role sistem tidak dapat dihapus.');
                }

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
     * Hapus role dikunci ke super_admin — bukan sekadar permission biasa,
     * supaya role tenant tidak dapat membuka akses ini lewat toggle.
     *
     * @throws \DomainException
     */
    private function ensureActorIsSuperAdmin(User $actor): void
    {
        if (! $actor->isSuperAdmin()) {
            throw new \DomainException('Hanya super admin yang dapat menghapus role.');
        }
    }

    /**
     * Role sistem adalah jangkar identitas keamanan dan tidak boleh diedit
     * lewat UI. Role tenant biasa hanya dapat diubah oleh aktor tenant yang
     * sama atau oleh super admin global.
     */
    private function ensureActorCanUpdate(User $actor, Role $role): void
    {
        if ($role->isSystem()) {
            throw new \DomainException('Role sistem tidak dapat diubah.');
        }

        if (! $actor->isSuperAdmin() && (int) $role->koperasi_id !== (int) $actor->koperasi_id) {
            throw new \DomainException('Role tidak berada dalam koperasi Anda.');
        }
    }
}
