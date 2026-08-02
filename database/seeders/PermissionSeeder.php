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
     * Seed katalog permission + role `super_admin` global. Super admin hanya
     * mendapat akses control-plane dan baca lintas koperasi; permission
     * mutasi operasional tetap milik role tenant seperti admin_primer.
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

        $superAdminRole = Role::query()
            ->where('name', 'super_admin')
            ->where('guard_name', 'web')
            ->whereNull('koperasi_id')
            ->first();

        if (! $superAdminRole) {
            $superAdminRole = new Role(['name' => 'super_admin', 'guard_name' => 'web']);
            $superAdminRole->koperasi_id = null;
            $superAdminRole->save();
        }

        $superAdminRole->syncPermissions(
            $permissionModels->whereIn('name', PermissionCatalog::superAdminTemplate())
        );

        $registrar->forgetCachedPermissions();
    }
}
