<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use App\Repositories\UserRepository;
use App\Support\PerPage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Exceptions\RoleDoesNotExist;

class UserService
{
    public function __construct(private UserRepository $userRepository) {}

    /**
     * @param  array{search?: ?string, role?: ?string}  $filters
     */
    public function list(array $filters, int $perPage = PerPage::DEFAULT): LengthAwarePaginator
    {
        return $this->userRepository->paginate($filters, $perPage);
    }

    /**
     * @throws \DomainException Jika aktor mencoba menetapkan role
     *                          super_admin/admin_primer tanpa jadi super_admin.
     */
    public function store(User $actor, array $data): User
    {
        $role = $this->resolveAssignableRole($actor, $data['role']);

        return DB::transaction(function () use ($data, $role) {
            $user = $this->userRepository->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'unit_kerja_id' => $data['unit_kerja_id'] ?? null,
            ]);

            $user->syncRoles([$role]);

            return $user;
        }, 3);
    }

    /**
     * @throws \DomainException Jika aktor mencoba menetapkan role
     *                          super_admin/admin_primer tanpa jadi super_admin,
     *                          atau mengelola akun yang bukan wewenangnya.
     */
    public function update(User $actor, User $user, array $data): User
    {
        $this->ensureCanManage($actor, $user);

        $role = $this->resolveAssignableRole($actor, $data['role']);

        return DB::transaction(function () use ($user, $data, $role) {
            $this->userRepository->update($user, [
                'name' => $data['name'],
                'email' => $data['email'],
                'unit_kerja_id' => $data['unit_kerja_id'] ?? null,
                ...(filled($data['password'] ?? null) ? ['password' => Hash::make($data['password'])] : []),
            ]);

            $user->syncRoles([$role]);

            return $user;
        }, 3);
    }

    /**
     * @throws \DomainException Jika actor mencoba menghapus akunnya sendiri,
     *                          atau mengelola akun yang bukan wewenangnya.
     */
    public function destroy(User $actor, User $target): void
    {
        if ($actor->is($target)) {
            throw new \DomainException('Anda tidak dapat menghapus akun Anda sendiri.');
        }

        $this->ensureCanManage($actor, $target);

        $this->userRepository->delete($target);
    }

    /**
     * Guard di layer service (bukan cuma Policy) supaya jalur seperti
     * bulkDestroy — yang otorisasinya cuma dicek di level kelas User, bukan
     * per-baris — tetap tidak bisa dipakai admin_primer buat mengubah/
     * menghapus akun super_admin/admin_primer atau akun koperasi lain lewat
     * ID yang ditebak (IDOR). Aturan sama persis dengan UserPolicy::canManage.
     *
     * @throws \DomainException
     */
    private function ensureCanManage(User $actor, User $target): void
    {
        if ($actor->isSuperAdmin()) {
            return;
        }

        if ($target->isSuperAdmin() || $target->isAdminPrimer()) {
            throw new \DomainException('Anda tidak memiliki izin untuk mengelola akun ini.');
        }

        if ($target->koperasi_id !== $actor->koperasi_id) {
            throw new \DomainException('Anda tidak memiliki izin untuk mengelola akun ini.');
        }
    }

    /**
     * Resolve role berdasarkan nama, di-scope ke koperasi aktor (kecuali
     * super_admin). Role TIDAK pakai global scope tenant (lihat catatan di
     * App\Models\Role), dan banyak koperasi bisa punya role dengan nama
     * SAMA PERSIS (mis. tiap koperasi punya "admin_primer" sendiri) — kalau
     * di-assign lewat nama tanpa filter koperasi_id di sini, Spatie bisa
     * salah pilih role milik tenant lain. Sekalian menolak penetapan role
     * super_admin/admin_primer oleh aktor yang bukan super_admin —
     * hardcoded, bukan sekadar permission checkbox.
     *
     * @throws \DomainException
     * @throws RoleDoesNotExist
     */
    private function resolveAssignableRole(User $actor, string $roleName): Role
    {
        if (in_array($roleName, ['super_admin', 'admin_primer'], true) && ! $actor->isSuperAdmin()) {
            throw new \DomainException('Anda tidak memiliki izin untuk menetapkan role ini.');
        }

        $query = Role::query()->where('name', $roleName)->where('guard_name', 'web');

        if (! $actor->isSuperAdmin()) {
            $query->where('koperasi_id', $actor->koperasi_id);
        }

        return $query->first() ?? throw RoleDoesNotExist::named($roleName, 'web');
    }
}
