<?php

namespace App\Services;

use App\Models\Koperasi;
use App\Models\Role;
use App\Models\User;
use App\Repositories\KoperasiRepository;
use App\Support\PerPage;
use App\Support\PermissionCatalog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class KoperasiService
{
    public function __construct(private KoperasiRepository $koperasiRepository) {}

    public function list(?string $search, int $perPage = PerPage::DEFAULT): LengthAwarePaginator
    {
        return $this->koperasiRepository->paginate($search, $perPage);
    }

    /**
     * Provisioning koperasi baru: buat koperasi, role admin_primer khusus
     * tenant ini, dan akun admin_primer pertamanya — satu transaction.
     *
     * koperasi_id diisi manual (bukan lewat auto-assign trait) karena aktor
     * yang menjalankan ini (super_admin) tidak terikat koperasi manapun.
     */
    public function store(array $data): Koperasi
    {
        return DB::transaction(function () use ($data) {
            $koperasi = $this->koperasiRepository->create([
                'nama' => $data['nama'],
                'expires_at' => $data['expires_at'] ?? null,
                'is_active' => true,
            ]);

            $role = new Role(['name' => 'admin_primer', 'guard_name' => 'web']);
            $role->koperasi_id = $koperasi->id;
            $role->save();
            $role->syncPermissions(PermissionCatalog::adminPrimerTemplate());

            $user = new User([
                'name' => $data['admin_nama'],
                'email' => $data['admin_email'],
                'password' => Hash::make($data['admin_password']),
            ]);
            $user->koperasi_id = $koperasi->id;
            $user->save();
            $user->assignRole($role);

            return $koperasi;
        }, 3);
    }

    public function update(Koperasi $koperasi, array $data): Koperasi
    {
        return DB::transaction(fn () => $this->koperasiRepository->update($koperasi, [
            'nama' => $data['nama'],
            'expires_at' => $data['expires_at'] ?? null,
            'is_active' => $data['is_active'],
        ]), 3);
    }
}
