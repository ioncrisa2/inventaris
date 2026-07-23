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
                    'karyawan.update' => 'Ubah',
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
            'barang' => [
                'label' => 'Inventaris Barang',
                'permissions' => [
                    'barang.view' => 'Lihat',
                    'barang.create' => 'Tambah',
                    'barang.update' => 'Ubah',
                    'barang.delete' => 'Hapus',
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
                ],
            ],
            'laporan' => [
                'label' => 'Laporan',
                'permissions' => [
                    'laporan.inventaris.view' => 'Laporan Inventaris',
                    'laporan.absensi.view' => 'Laporan Absensi',
                    'laporan.kepegawaian.view' => 'Laporan Kepegawaian',
                    'laporan.penggajian.view' => 'Laporan Penggajian',
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
                    'pengaturan.update' => 'Ubah',
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
}
