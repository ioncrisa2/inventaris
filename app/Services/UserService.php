<?php

namespace App\Services;

use App\Models\Role;
use App\Models\UnitKerja;
use App\Models\User;
use App\Repositories\UserRepository;
use App\Support\PerPage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Exceptions\RoleDoesNotExist;

class UserService
{
    public function __construct(private UserRepository $userRepository) {}

    /**
     * @param  array{search?: ?string, role_id?: ?string, koperasi_id?: ?int}  $filters
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
        $role = $this->resolveAssignableRole($actor, (int) $data['role_id']);
        $this->ensureUnitBelongsToTenant($data['unit_kerja_id'] ?? null, $role->koperasi_id);

        return DB::transaction(function () use ($data, $role) {
            $user = $this->userRepository->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'unit_kerja_id' => $data['unit_kerja_id'] ?? null,
                'koperasi_id' => $role->koperasi_id,
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

        $role = $this->resolveAssignableRole($actor, (int) $data['role_id']);
        $this->ensureUnitBelongsToTenant($data['unit_kerja_id'] ?? null, $role->koperasi_id);

        return DB::transaction(function () use ($user, $data, $role) {
            $this->ensureTenantKeepsAdminPrimer($user, $role);

            $this->userRepository->update($user, [
                'name' => $data['name'],
                'email' => $data['email'],
                'unit_kerja_id' => $data['unit_kerja_id'] ?? null,
                'koperasi_id' => $role->koperasi_id,
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

        DB::transaction(function () use ($target) {
            $this->ensureTenantKeepsAdminPrimer($target);
            $this->userRepository->delete($target);
        }, 3);
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
     * Menjaga setiap koperasi tetap memiliki minimal satu admin primer.
     * Query seluruh akun admin primer dikunci agar dua request paralel tidak
     * sama-sama melihat "masih ada satu admin lain" lalu menghapus keduanya.
     *
     * Saat update, replacementRole menunjukkan role yang akan dipasang. Tidak
     * perlu melakukan guard bila akun tetap menjadi admin primer di koperasi
     * yang sama.
     *
     * @throws \DomainException
     */
    private function ensureTenantKeepsAdminPrimer(User $target, ?Role $replacementRole = null): void
    {
        if (! $target->isAdminPrimer()) {
            return;
        }

        $remainsAdminPrimer = $replacementRole?->isAdminPrimerRole()
            && (int) $replacementRole->koperasi_id === (int) $target->koperasi_id;

        if ($remainsAdminPrimer) {
            return;
        }

        $koperasiId = (int) $target->koperasi_id;
        $adminPrimerIds = User::query()
            ->where('koperasi_id', $koperasiId)
            ->whereHas('roles', fn ($query) => $query
                ->where('roles.name', 'admin_primer')
                ->where('roles.koperasi_id', $koperasiId))
            ->orderBy('users.id')
            ->lockForUpdate()
            ->pluck('users.id');

        if ($adminPrimerIds->count() <= 1) {
            throw new \DomainException(
                'Akun ini adalah admin primer terakhir. Tambahkan admin primer pengganti sebelum mengubah role atau menghapus akun ini.'
            );
        }
    }

    /**
     * Resolve role berdasarkan primary key dan scope koperasi aktor. Nama
     * role tidak cukup sebagai identitas karena banyak koperasi boleh punya
     * nama role yang sama. Role global hanya sah untuk super_admin; role
     * sistem tenant tidak dapat ditetapkan oleh aktor non-super.
     *
     * @throws \DomainException
     * @throws RoleDoesNotExist
     */
    private function resolveAssignableRole(User $actor, int $roleId): Role
    {
        $query = Role::query()->whereKey($roleId)->where('guard_name', 'web');

        if (! $actor->isSuperAdmin()) {
            $query->where('koperasi_id', $actor->koperasi_id);
        }

        $role = $query->first() ?? throw RoleDoesNotExist::withId($roleId, 'web');

        if ($role->koperasi_id === null && ! $role->isSuperAdminRole()) {
            throw new \DomainException('Role global tanpa koperasi tidak dapat ditetapkan kepada pengguna.');
        }

        if ($role->isSystem() && ! $actor->isSuperAdmin()) {
            throw new \DomainException('Anda tidak memiliki izin untuk menetapkan role ini.');
        }

        return $role;
    }

    /**
     * Unit kerja dan role menentukan tenant user yang sama. Query sengaja
     * melepas global scope lalu memasang kedua ID secara eksplisit supaya
     * tetap benar saat super admin membuat/memindahkan user lintas koperasi.
     */
    private function ensureUnitBelongsToTenant(mixed $unitKerjaId, ?int $koperasiId): void
    {
        if (blank($unitKerjaId)) {
            return;
        }

        $valid = $koperasiId !== null
            && UnitKerja::withoutGlobalScopes()
                ->whereKey((int) $unitKerjaId)
                ->where('koperasi_id', $koperasiId)
                ->exists();

        if (! $valid) {
            throw ValidationException::withMessages([
                'unit_kerja_id' => 'Unit kerja tidak berada dalam koperasi role yang dipilih.',
            ]);
        }
    }
}
