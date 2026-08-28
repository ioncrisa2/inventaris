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
     * Seed katalog permission serta role platform global. System owner tidak
     * menerima permission tenant; area owner dikunci dengan middleware
     * identitas. Super admin hanya mendapat akses control-plane dan baca
     * lintas koperasi.
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

        $systemOwnerRoles = Role::query()
            ->where('name', 'system_owner')
            ->where('guard_name', 'web')
            ->whereNull('koperasi_id')
            ->get();

        if ($systemOwnerRoles->count() > 1) {
            throw new \RuntimeException('Ditemukan lebih dari satu role global system_owner untuk guard web.');
        }

        $systemOwnerRole = $systemOwnerRoles->first();

        if (! $systemOwnerRole) {
            $systemOwnerRole = new Role(['name' => 'system_owner', 'guard_name' => 'web']);
            $systemOwnerRole->koperasi_id = null;
            $systemOwnerRole->save();
        }

        // Jangan pernah mewariskan katalog permission tenant kepada owner.
        $systemOwnerRole->syncPermissions([]);

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
