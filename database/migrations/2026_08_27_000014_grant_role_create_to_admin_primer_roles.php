<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $tables = config('permission.table_names');
        $columns = config('permission.column_names');
        $permissionKey = $columns['permission_pivot_key'] ?? 'permission_id';
        $roleKey = $columns['role_pivot_key'] ?? 'role_id';

        $permissionId = DB::table($tables['permissions'])
            ->where('name', 'role.create')
            ->where('guard_name', 'web')
            ->value('id');

        if ($permissionId === null) {
            return;
        }

        $rows = DB::table($tables['roles'])
            ->where('name', 'admin_primer')
            ->where('guard_name', 'web')
            ->whereNotNull('koperasi_id')
            ->pluck('id')
            ->map(fn ($roleId) => [
                $permissionKey => $permissionId,
                $roleKey => $roleId,
            ])
            ->all();

        if ($rows !== []) {
            DB::table($tables['role_has_permissions'])->insertOrIgnore($rows);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $tables = config('permission.table_names');
        $columns = config('permission.column_names');
        $permissionKey = $columns['permission_pivot_key'] ?? 'permission_id';
        $roleKey = $columns['role_pivot_key'] ?? 'role_id';

        $permissionId = DB::table($tables['permissions'])
            ->where('name', 'role.create')
            ->where('guard_name', 'web')
            ->value('id');

        if ($permissionId === null) {
            return;
        }

        $adminPrimerRoleIds = DB::table($tables['roles'])
            ->where('name', 'admin_primer')
            ->where('guard_name', 'web')
            ->whereNotNull('koperasi_id')
            ->pluck('id');

        DB::table($tables['role_has_permissions'])
            ->where($permissionKey, $permissionId)
            ->whereIn($roleKey, $adminPrimerRoleIds)
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
