<?php

namespace Database\Seeders;

use App\Models\KomponenGaji;
use Illuminate\Database\Seeder;

class KomponenGajiSeeder extends Seeder
{
    public function run(): void
    {
        $komponen = [
            [
                'nama_komponen' => 'Tunjangan Jabatan',
                'jenis' => 'Tunjangan',
                'metode_perhitungan' => 'persentase',
                'nilai_default' => 10,
                'dasar_persentase' => 'gaji_pokok',
            ],
            [
                'nama_komponen' => 'Tunjangan Transport',
                'jenis' => 'Tunjangan',
                'metode_perhitungan' => 'nominal_tetap',
                'nilai_default' => 500000,
                'dasar_persentase' => null,
            ],
            [
                'nama_komponen' => 'Potongan BPJS Kesehatan',
                'jenis' => 'Potongan',
                'metode_perhitungan' => 'persentase',
                'nilai_default' => 2,
                'dasar_persentase' => 'gaji_pokok',
            ],
            [
                'nama_komponen' => 'Potongan Keterlambatan',
                'jenis' => 'Potongan',
                'metode_perhitungan' => 'nominal_tetap',
                'nilai_default' => 50000,
                'dasar_persentase' => null,
            ],
            [
                'nama_komponen' => 'Tunjangan Uang Makan',
                'jenis' => 'Tunjangan',
                'metode_perhitungan' => 'per_kehadiran',
                'nilai_default' => 30000,
                'dasar_persentase' => null,
            ],
            [
                'nama_komponen' => 'Potongan BPJS Ketenagakerjaan',
                'jenis' => 'Potongan',
                'metode_perhitungan' => 'persentase',
                'nilai_default' => 2,
                'dasar_persentase' => 'gaji_pokok',
            ],
            [
                'nama_komponen' => 'Potongan Pajak PPh 21',
                'jenis' => 'Potongan',
                'metode_perhitungan' => 'nominal_tetap',
                'nilai_default' => 100000,
                'dasar_persentase' => null,
            ],
        ];

        foreach ($komponen as $data) {
            KomponenGaji::updateOrCreate(
                ['nama_komponen' => $data['nama_komponen']],
                $data,
            );
        }
    }
}
