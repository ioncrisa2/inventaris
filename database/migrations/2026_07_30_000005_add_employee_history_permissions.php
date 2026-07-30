<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const PERMISSIONS = [
        'karyawan.kepegawaian.update',
        'karyawan.gaji.view',
        'karyawan.gaji.update',
        'karyawan.riwayat.view',
    ];

    public function up(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        $permissions = collect(self::PERMISSIONS)
            ->map(fn (string $name) => Permission::findOrCreate($name, 'web'));

        Role::findOrCreate('Admin', 'web')->givePermissionTo($permissions);
        Role::findOrCreate('Staff', 'web')->givePermissionTo('karyawan.riwayat.view');

        $registrar->forgetCachedPermissions();
    }

    public function down(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        Permission::query()
            ->where('guard_name', 'web')
            ->whereIn('name', self::PERMISSIONS)
            ->get()
            ->each
            ->delete();

        $registrar->forgetCachedPermissions();
    }
};
