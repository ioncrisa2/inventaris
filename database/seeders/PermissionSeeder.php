<?php

namespace Database\Seeders;

use App\Support\PermissionCatalog;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
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

        Role::findOrCreate('Admin', 'web')
            ->syncPermissions($permissionModels);

        Role::findOrCreate('Staff', 'web')
            ->syncPermissions($permissionModels->whereIn('name', [
                'dashboard.total-inventaris.view',
                'dashboard.nilai-aset.view',
                'dashboard.perlu-perbaikan.view',
                'dashboard.karyawan-aktif.view',
                'dashboard.tren-absensi.view',
                'dashboard.kondisi-inventaris.view',
                'dashboard.data-belum-lengkap.view',
                'unit-kerja.view',
                'karyawan.view',
                'karyawan.create',
                'karyawan.update',
                'absensi.view',
                'absensi.create',
                'barang.view',
                'barang.create',
                'barang.update',
                'komponen-gaji.view',
                'transaksi-gaji.view',
                'laporan.inventaris.view',
                'laporan.absensi.view',
                'laporan.kepegawaian.view',
                'laporan.penggajian.view',
            ]));

        $registrar->forgetCachedPermissions();
    }
}
