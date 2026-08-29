<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class HariLiburSeeder extends Seeder
{
    public function run(): void
    {
        $tahun = now()->year;

        $hariLiburs = [
            ['tanggal' => "{$tahun}-01-01", 'keterangan' => 'Tahun Baru Masehi'],
            ['tanggal' => "{$tahun}-08-17", 'keterangan' => 'Hari Kemerdekaan Republik Indonesia'],
            ['tanggal' => "{$tahun}-12-25", 'keterangan' => 'Hari Raya Natal'],
        ];

        foreach ($hariLiburs as $hariLibur) {
            DB::table('hari_libur')->updateOrInsert(
                [
                    'cakupan_id' => 0,
                    'tanggal' => $hariLibur['tanggal'],
                ],
                [
                    'koperasi_id' => null,
                    'keterangan' => $hariLibur['keterangan'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }
    }
}
