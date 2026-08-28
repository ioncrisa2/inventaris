<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /** @var list<string> */
    private array $permissions = [
        'product-request.view',
        'product-request.create',
        'product-request.reply',
        'product-request.close',
    ];

    public function up(): void
    {
        $tables = config('permission.table_names');
        $columns = config('permission.column_names');
        $permissionKey = $columns['permission_pivot_key'] ?? 'permission_id';
        $roleKey = $columns['role_pivot_key'] ?? 'role_id';

        foreach ($this->permissions as $permission) {
            DB::table($tables['permissions'])->insertOrIgnore([
                'name' => $permission,
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $permissionIds = DB::table($tables['permissions'])
            ->where('guard_name', 'web')
            ->whereIn('name', $this->permissions)
            ->pluck('id');
        $roleIds = DB::table($tables['roles'])
            ->where('name', 'admin_primer')
            ->where('guard_name', 'web')
            ->whereNotNull('koperasi_id')
            ->pluck('id');

        $rows = $roleIds
            ->crossJoin($permissionIds)
            ->map(fn (array $ids) => [
                $roleKey => $ids[0],
                $permissionKey => $ids[1],
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

        $permissionIds = DB::table($tables['permissions'])
            ->where('guard_name', 'web')
            ->whereIn('name', $this->permissions)
            ->pluck('id');

        DB::table($tables['role_has_permissions'])
            ->whereIn($permissionKey, $permissionIds)
            ->delete();
        DB::table($tables['model_has_permissions'])
            ->whereIn($permissionKey, $permissionIds)
            ->delete();
        DB::table($tables['permissions'])
            ->whereIn('id', $permissionIds)
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
