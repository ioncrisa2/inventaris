<?php

use App\Support\PermissionCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $superAdminRoleIds = DB::table('roles')
            ->where('name', 'super_admin')
            ->where('guard_name', 'web')
            ->whereNull('koperasi_id')
            ->pluck('id');
        $allowedPermissionIds = DB::table('permissions')
            ->where('guard_name', 'web')
            ->whereIn('name', PermissionCatalog::superAdminTemplate())
            ->pluck('id');

        DB::table('role_has_permissions')
            ->whereIn('role_id', $superAdminRoleIds)
            ->whereNotIn('permission_id', $allowedPermissionIds)
            ->delete();

        $roleIds = DB::table('roles')
            ->whereNull('koperasi_id')
            ->whereNotIn('name', ['super_admin'])
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('model_has_roles')
                    ->whereColumn('model_has_roles.role_id', 'roles.id');
            })
            ->pluck('id');

        if ($roleIds->isNotEmpty()) {
            DB::table('role_has_permissions')->whereIn('role_id', $roleIds)->delete();
            DB::table('roles')->whereIn('id', $roleIds)->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Data role yatim yang tidak dipakai sengaja tidak direkonstruksi.
    }
};
