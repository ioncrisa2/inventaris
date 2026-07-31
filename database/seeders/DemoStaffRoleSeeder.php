<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class DemoStaffRoleSeeder extends Seeder
{
    /**
     * Role "Staff" contoh untuk data demo/pengembangan — BUKAN role sistem
     * seperti super_admin/admin_primer. Di dunia nyata, role seperti ini
     * dibuat manual oleh super_admin untuk masing-masing koperasi (lihat
     * docs/multi-tenant-koperasi.md), bukan lewat seeder global.
     *
     * Role TIDAK pakai global scope tenant (lihat App\Models\Role), jadi
     * koperasi_id harus diisi manual di sini — diambil dari user yang
     * sedang "aktif" (DatabaseSeeder men-set Auth::setUser() ke akun
     * admin_primer sebelum memanggil seeder ini) supaya role Staff demo
     * ini benar-benar milik koperasi demo, bukan global/null.
     */
    public function run(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        $permissionNames = [
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
            'karyawan.riwayat.view',
            'absensi.view',
            'absensi.create',
            'hari-libur.view',
            'barang.view',
            'barang.create',
            'barang.update',
            'barang.kondisi.catat',
            'barang.foto.kelola',
            'barang.dokumen.kelola',
            'komponen-gaji.view',
            'transaksi-gaji.view',
            'transaksi-gaji.cetak',
            'laporan.inventaris.view',
            'laporan.inventaris.cetak',
            'laporan.inventaris.export',
            'laporan.absensi.view',
            'laporan.absensi.cetak',
            'laporan.absensi.export',
            'laporan.kepegawaian.view',
            'laporan.kepegawaian.cetak',
            'laporan.kepegawaian.export',
            'laporan.penggajian.view',
            'laporan.penggajian.cetak',
            'laporan.penggajian.export',
            'laporan.penyusutan.view',
            'laporan.penyusutan.cetak',
            'laporan.penyusutan.export',
        ];

        $permissionModels = Permission::query()
            ->where('guard_name', 'web')
            ->whereIn('name', $permissionNames)
            ->get();

        $role = Role::findOrCreate('Staff', 'web');
        $role->koperasi_id ??= auth()->user()?->koperasi_id;
        $role->save();
        $role->syncPermissions($permissionModels);

        $registrar->forgetCachedPermissions();
    }
}
