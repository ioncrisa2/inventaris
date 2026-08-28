<?php

namespace App\Support;

use App\Models\User;

class KaryawanPerubahanSchema
{
    public static function types(): array
    {
        return [
            'data_pribadi' => [
                'label' => 'Perubahan Data Pribadi',
                'description' => 'Identitas, alamat, status perkawinan, pendidikan, dan data keluarga.',
                'permission' => 'karyawan.update',
                'dokumen_wajib' => false,
                'fields' => [
                    'nik',
                    'nama_lengkap',
                    'tempat_lahir',
                    'tanggal_lahir',
                    'jenis_kelamin',
                    'agama',
                    'nomor_ktp',
                    'npwp',
                    'alamat_ktp',
                    'alamat_domisili',
                    'status_perkawinan',
                    'pendidikan_terakhir',
                    'jurusan',
                    'nama_sekolah',
                    'tahun_lulus',
                    'nama_pasangan',
                    'jumlah_anak',
                    'foto_karyawan',
                ],
            ],
            'hubungan_kerja' => [
                'label' => 'Perubahan Hubungan Kerja',
                'description' => 'Perubahan PKWT, PKWTT, atau Honorer beserta dasar keputusannya.',
                'permission' => 'karyawan.kepegawaian.update',
                'dokumen_wajib' => true,
                'fields' => [
                    'tanggal_masuk_kerja',
                    'status_karyawan',
                    'nomor_sk_pengangkatan',
                    'tanggal_sk_pengangkatan',
                ],
            ],
            'mutasi_promosi' => [
                'label' => 'Mutasi atau Promosi',
                'description' => 'Perubahan unit kerja, jabatan, atasan langsung, dan SK.',
                'permission' => 'karyawan.kepegawaian.update',
                'dokumen_wajib' => true,
                'fields' => [
                    'unit_kerja_id',
                    'jabatan',
                    'atasan_langsung_id',
                    'nomor_sk_pengangkatan',
                    'tanggal_sk_pengangkatan',
                ],
            ],
            'penyesuaian_gaji' => [
                'label' => 'Penyesuaian Gaji',
                'description' => 'Kenaikan atau koreksi gaji pokok yang berlaku pada tanggal tertentu.',
                'permission' => 'karyawan.gaji.update',
                'dokumen_wajib' => true,
                'fields' => ['gaji_pokok'],
            ],
            'keaktifan' => [
                'label' => 'Perubahan Keaktifan',
                'description' => 'Menonaktifkan atau mengaktifkan kembali karyawan.',
                'permission' => 'karyawan.kepegawaian.update',
                'dokumen_wajib' => true,
                'fields' => ['tanggal_mengundurkan_diri'],
            ],
        ];
    }

    public static function fields(): array
    {
        return [
            'nik' => 'NIK',
            'nama_lengkap' => 'Nama Lengkap',
            'tempat_lahir' => 'Tempat Lahir',
            'tanggal_lahir' => 'Tanggal Lahir',
            'jenis_kelamin' => 'Jenis Kelamin',
            'agama' => 'Agama',
            'status_perkawinan' => 'Status Perkawinan',
            'nomor_ktp' => 'Nomor KTP',
            'npwp' => 'NPWP',
            'pendidikan_terakhir' => 'Pendidikan Terakhir',
            'jurusan' => 'Jurusan',
            'nama_sekolah' => 'Sekolah/Universitas',
            'tahun_lulus' => 'Tahun Lulus',
            'nama_pasangan' => 'Nama Pasangan',
            'jumlah_anak' => 'Jumlah Anak',
            'alamat_ktp' => 'Alamat sesuai KTP',
            'alamat_domisili' => 'Alamat Domisili',
            'foto_karyawan' => 'Foto Karyawan',
            'unit_kerja_id' => 'Unit Kerja',
            'tanggal_masuk_kerja' => 'Tanggal Masuk Kerja',
            'jabatan' => 'Jabatan',
            'status_karyawan' => 'Hubungan Kerja',
            'nomor_sk_pengangkatan' => 'Nomor SK',
            'tanggal_sk_pengangkatan' => 'Tanggal SK',
            'atasan_langsung_id' => 'Atasan Langsung',
            'gaji_pokok' => 'Gaji Pokok',
            'tanggal_mengundurkan_diri' => 'Tanggal Keluar',
        ];
    }

    public static function allowedTypesFor(User $user): array
    {
        return collect(self::types())
            ->filter(fn (array $type) => $user->can($type['permission']))
            ->all();
    }

    public static function sensitiveFields(): array
    {
        return ['nomor_ktp', 'npwp', 'alamat_ktp', 'alamat_domisili', 'gaji_pokok'];
    }
}
