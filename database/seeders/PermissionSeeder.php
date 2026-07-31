<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Support\PermissionCatalog;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    /**
     * Seed katalog permission + role sistem `super_admin` (global, semua
     * permission). Role per-koperasi seperti `admin_primer` TIDAK di-seed
     * di sini — itu dibuat saat sebuah koperasi di-provisioning (lihat
     * docs/multi-tenant-koperasi.md), karena role tersebut terikat ke satu
     * koperasi tertentu, bukan role global.
     */
    public function run(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();
        $semuaPermission = PermissionCatalog::all();

        foreach ($semuaPermission as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $registrar->forgetCachedPermissions();
        $permissionModels = Permission::query()
            ->where('guard_name', 'web')
            ->whereIn('name', $semuaPermission)
            ->get();

        Role::findOrCreate('super_admin', 'web')
            ->syncPermissions($permissionModels);

        $registrar->forgetCachedPermissions();
    }
}
