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
     * @throws \DomainException Jika aktor bukan pengelola role yang sah.
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
        if ($actor->isSystemOwner() || $actor->isSuperAdmin()) {
            if ($requestedKoperasiId === null) {
                throw new \DomainException('Koperasi tujuan wajib dipilih.');
            }

            return $requestedKoperasiId;
        }

        if ($actor->isAdminPrimer() && $actor->koperasi_id !== null) {
            return (int) $actor->koperasi_id;
        }

        throw new \DomainException('Hanya system owner, super admin, atau admin primer yang dapat membuat role.');
    }

    public function update(User $actor, Role $role, array $data): Role
    {
        $this->ensureActorCanUpdate($actor, $role);

        return DB::transaction(function () use ($role, $data) {
            // Nama role sistem adalah jangkar identitas. System Owner boleh
            // mengatur permission-nya, tetapi tidak boleh mengganti namanya.
            if (! $role->isSystem()) {
                $this->roleRepository->update($role, $data['name']);
            }
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
        $this->ensureActorCanDelete($actor);

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
    private function ensureActorCanDelete(User $actor): void
    {
        if (! $actor->isSystemOwner() && ! $actor->isSuperAdmin()) {
            throw new \DomainException('Hanya system owner atau super admin yang dapat menghapus role.');
        }
    }

    /**
     * Nama role sistem tetap menjadi jangkar identitas. System owner dapat
     * mengatur permission role sistem selain miliknya sendiri; super admin
     * hanya dapat mengatur permission Admin Primer. Role custom mengikuti
     * lingkup tenant aktor atau akses global platform.
     */
    private function ensureActorCanUpdate(User $actor, Role $role): void
    {
        if ($role->name === 'system_owner') {
            throw new \DomainException('Role system owner tidak dapat dikelola dari aplikasi.');
        }

        if ($role->isSystem()) {
            if ($actor->isSystemOwner() || ($actor->isSuperAdmin() && $role->isAdminPrimerRole())) {
                return;
            }

            throw new \DomainException('Role sistem tidak dapat diubah.');
        }

        if (! $actor->isSystemOwner() && ! $actor->isSuperAdmin() && (int) $role->koperasi_id !== (int) $actor->koperasi_id) {
            throw new \DomainException('Role tidak berada dalam koperasi Anda.');
        }
    }
}
