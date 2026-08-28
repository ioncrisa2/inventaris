<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private string $permission = 'transaksi-gaji.publish';

    public function up(): void
    {
        Schema::table('transaksi_gaji', function (Blueprint $table) {
            $table->timestamp('published_at')->nullable()->after('gaji_bersih')->index();
            $table->foreignId('published_by')->nullable()->after('published_at')->constrained('users')->nullOnDelete();
        });

        DB::table('transaksi_gaji')->whereNull('published_at')->update([
            'published_at' => DB::raw('created_at'),
        ]);

        Schema::create('salary_access_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('transaksi_gaji_id')->nullable()->constrained('transaksi_gaji')->nullOnDelete();
            $table->string('action', 20);
            $table->timestamp('created_at')->useCurrent();
            $table->index(['actor_user_id', 'created_at']);
            $table->index(['transaksi_gaji_id', 'created_at']);
        });

        $this->grantPermission();
    }

    public function down(): void
    {
        $this->revokePermission();
        Schema::dropIfExists('salary_access_logs');

        Schema::table('transaksi_gaji', function (Blueprint $table) {
            $table->dropConstrainedForeignId('published_by');
            $table->dropColumn('published_at');
        });
    }

    private function grantPermission(): void
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

    private function revokePermission(): void
    {
        $tables = config('permission.table_names');
        $columns = config('permission.column_names');
        $permissionKey = $columns['permission_pivot_key'] ?? 'permission_id';
        $ids = DB::table($tables['permissions'])
            ->where('name', $this->permission)
            ->where('guard_name', 'web')
            ->pluck('id');

        DB::table($tables['role_has_permissions'])->whereIn($permissionKey, $ids)->delete();
        DB::table($tables['model_has_permissions'])->whereIn($permissionKey, $ids)->delete();
        DB::table($tables['permissions'])->whereIn('id', $ids)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
