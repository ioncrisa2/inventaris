<?php

namespace App\Support;

class PermissionCatalog
{
    /**
     * Daftar seluruh permission aplikasi, dikelompokkan per modul supaya
     * bisa dipakai ulang oleh PermissionSeeder dan halaman kelola role.
     *
     * @return array<string, array{label: string, permissions: array<string, string>}>
     */
    public static function groups(): array
    {
        return [
            'dashboard' => [
                'label' => 'Dashboard',
                'permissions' => [
                    'dashboard.total-inventaris.view' => 'Kartu Total Inventaris',
                    'dashboard.nilai-aset.view' => 'Kartu Nilai Aset',
                    'dashboard.perlu-perbaikan.view' => 'Kartu Perlu Perbaikan',
                    'dashboard.karyawan-aktif.view' => 'Kartu Karyawan Aktif',
                    'dashboard.tren-absensi.view' => 'Grafik Tren Absensi Periode Penggajian',
                    'dashboard.kondisi-inventaris.view' => 'Grafik Kondisi Inventaris',
                    'dashboard.data-belum-lengkap.view' => 'Daftar Data Belum Lengkap',
                ],
            ],
            'unit-kerja' => [
                'label' => 'Unit Kerja',
                'permissions' => [
                    'unit-kerja.view' => 'Lihat',
                    'unit-kerja.create' => 'Tambah',
                    'unit-kerja.update' => 'Ubah',
                    'unit-kerja.delete' => 'Hapus',
                ],
            ],
            'karyawan' => [
                'label' => 'Karyawan',
                'permissions' => [
                    'karyawan.view' => 'Lihat',
                    'karyawan.create' => 'Tambah',
                    'karyawan.update' => 'Ubah Data Pribadi',
                    'karyawan.kepegawaian.update' => 'Ubah Data Kepegawaian',
                    'karyawan.gaji.view' => 'Lihat Gaji',
                    'karyawan.gaji.update' => 'Ubah Gaji',
                    'karyawan.riwayat.view' => 'Lihat Riwayat Perubahan',
                    'karyawan.delete' => 'Hapus',
                ],
            ],
            'absensi' => [
                'label' => 'Absensi',
                'permissions' => [
                    'absensi.view' => 'Lihat',
                    'absensi.create' => 'Input Absensi',
                ],
            ],
            'hari-libur' => [
                'label' => 'Hari Libur',
                'permissions' => [
                    'hari-libur.view' => 'Lihat',
                    'hari-libur.create' => 'Tambah',
                    'hari-libur.update' => 'Ubah',
                    'hari-libur.delete' => 'Hapus',
                ],
            ],
            'barang' => [
                'label' => 'Inventaris Barang',
                'permissions' => [
                    'barang.view' => 'Lihat',
                    'barang.create' => 'Tambah',
                    'barang.update' => 'Ubah',
                    'barang.delete' => 'Hapus',
                    'barang.kondisi.catat' => 'Catat Riwayat Kondisi',
                    'barang.foto.kelola' => 'Kelola Foto Pendukung',
                    'barang.dokumen.kelola' => 'Kelola Dokumen',
                ],
            ],
            'komponen-gaji' => [
                'label' => 'Komponen Gaji',
                'permissions' => [
                    'komponen-gaji.view' => 'Lihat',
                    'komponen-gaji.create' => 'Tambah',
                    'komponen-gaji.update' => 'Ubah',
                    'komponen-gaji.delete' => 'Hapus',
                ],
            ],
            'transaksi-gaji' => [
                'label' => 'Transaksi Gaji',
                'permissions' => [
                    'transaksi-gaji.view' => 'Lihat',
                    'transaksi-gaji.create' => 'Tambah',
                    'transaksi-gaji.update' => 'Ubah',
                    'transaksi-gaji.delete' => 'Hapus',
                    'transaksi-gaji.cetak' => 'Cetak Slip Gaji',
                ],
            ],
            'laporan' => [
                'label' => 'Laporan',
                'permissions' => [
                    'laporan.inventaris.view' => 'Laporan Inventaris — Lihat',
                    'laporan.inventaris.cetak' => 'Laporan Inventaris — Cetak',
                    'laporan.inventaris.export' => 'Laporan Inventaris — Export Excel',
                    'laporan.absensi.view' => 'Laporan Absensi — Lihat',
                    'laporan.absensi.cetak' => 'Laporan Absensi — Cetak',
                    'laporan.absensi.export' => 'Laporan Absensi — Export Excel',
                    'laporan.kepegawaian.view' => 'Laporan Kepegawaian — Lihat',
                    'laporan.kepegawaian.cetak' => 'Laporan Kepegawaian — Cetak',
                    'laporan.kepegawaian.export' => 'Laporan Kepegawaian — Export Excel',
                    'laporan.penggajian.view' => 'Laporan Penggajian — Lihat',
                    'laporan.penggajian.cetak' => 'Laporan Penggajian — Cetak',
                    'laporan.penggajian.export' => 'Laporan Penggajian — Export Excel',
                    'laporan.penyusutan.view' => 'Laporan Penyusutan — Lihat',
                    'laporan.penyusutan.cetak' => 'Laporan Penyusutan — Cetak',
                    'laporan.penyusutan.export' => 'Laporan Penyusutan — Export Excel',
                ],
            ],
            'pengguna' => [
                'label' => 'Manajemen Pengguna',
                'permissions' => [
                    'pengguna.view' => 'Lihat',
                    'pengguna.create' => 'Tambah',
                    'pengguna.update' => 'Ubah',
                    'pengguna.delete' => 'Hapus',
                ],
            ],
            'role' => [
                'label' => 'Role & Hak Akses',
                'permissions' => [
                    'role.view' => 'Lihat',
                    'role.create' => 'Tambah',
                    'role.update' => 'Ubah',
                    'role.delete' => 'Hapus',
                ],
            ],
            'pengaturan' => [
                'label' => 'Pengaturan Aplikasi',
                'permissions' => [
                    'pengaturan.view' => 'Lihat',
                    'pengaturan.kode-barang.update' => 'Ubah Penomoran Inventaris',
                    'pengaturan.hari-operasional.update' => 'Ubah Hari Operasional',
                    'pengaturan.identitas.update' => 'Ubah Identitas Koperasi',
                    'pengaturan.slip-gaji.update' => 'Ubah Draf Format Slip Gaji',
                    'pengaturan.slip-gaji.publish' => 'Terbitkan Format Slip Gaji',
                ],
            ],
        ];
    }

    /**
     * Daftar datar semua nama permission (mis. "karyawan.view").
     *
     * @return list<string>
     */
    public static function all(): array
    {
        return collect(self::groups())
            ->flatMap(fn (array $group) => array_keys($group['permissions']))
            ->values()
            ->all();
    }

    /**
     * Super admin adalah control-plane koperasi sekunder: boleh membaca data
     * lintas koperasi dan mengelola koperasi, pengguna, serta role, tetapi
     * tidak menulis data operasional koperasi primer. Tanpa tenant aktif,
     * mutasi operasional berisiko menghasilkan data yatim tanpa koperasi_id.
     *
     * @return list<string>
     */
    public static function superAdminTemplate(): array
    {
        $tenantMutationPermissions = [
            'unit-kerja.create',
            'unit-kerja.update',
            'unit-kerja.delete',
            'karyawan.create',
            'karyawan.update',
            'karyawan.kepegawaian.update',
            'karyawan.gaji.update',
            'karyawan.delete',
            'absensi.create',
            'hari-libur.create',
            'hari-libur.update',
            'hari-libur.delete',
            'barang.create',
            'barang.update',
            'barang.delete',
            'barang.kondisi.catat',
            'barang.foto.kelola',
            'barang.dokumen.kelola',
            'komponen-gaji.create',
            'komponen-gaji.update',
            'komponen-gaji.delete',
            'transaksi-gaji.create',
            'transaksi-gaji.update',
            'transaksi-gaji.delete',
            'transaksi-gaji.cetak',
            'pengaturan.kode-barang.update',
            'pengaturan.hari-operasional.update',
            'pengaturan.identitas.update',
            'pengaturan.slip-gaji.update',
            'pengaturan.slip-gaji.publish',
        ];

        return array_values(array_diff(self::all(), $tenantMutationPermissions));
    }

    /**
     * Permission untuk role sistem admin_primer (dipakai KoperasiService saat
     * provisioning koperasi baru) — seluruh permission KECUALI role.create
     * & role.delete. Pembuatan/penghapusan role dikunci ke super_admin lewat
     * guard eksplisit (RoleService/RoleController), bukan lewat permission
     * checkbox, jadi admin_primer memang tidak pernah diberi permission ini.
     *
     * @return list<string>
     */
    public static function adminPrimerTemplate(): array
    {
        return array_values(array_diff(self::all(), ['role.create', 'role.delete']));
    }
}
