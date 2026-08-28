<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private string $permission = 'karyawan.akun.update';

    public function up(): void
    {
        $tables = config('permission.table_names');
        $columns = config('permission.column_names');
        $permissionKey = $columns['permission_pivot_key'] ?? 'permission_id';
        $roleKey = $columns['role_pivot_key'] ?? 'role_id';

        DB::table($tables['permissions'])->insertOrIgnore([
            'name' => $this->permission,
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $permissionId = DB::table($tables['permissions'])
            ->where('name', $this->permission)
            ->where('guard_name', 'web')
            ->value('id');

        $roleIds = DB::table($tables['roles'])
            ->where('name', 'admin_primer')
            ->where('guard_name', 'web')
            ->whereNotNull('koperasi_id')
            ->pluck('id');

        $rows = $roleIds->map(fn (int $roleId) => [
            $roleKey => $roleId,
            $permissionKey => $permissionId,
        ])->all();

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

        $permissionIds = DB::table($tables['permissions'])
            ->where('name', $this->permission)
            ->where('guard_name', 'web')
            ->pluck('id');

        DB::table($tables['role_has_permissions'])->whereIn($permissionKey, $permissionIds)->delete();
        DB::table($tables['model_has_permissions'])->whereIn($permissionKey, $permissionIds)->delete();
        DB::table($tables['permissions'])->whereIn('id', $permissionIds)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
