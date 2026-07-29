<?php

namespace Database\Seeders;

use App\Models\HariLibur;
use Illuminate\Database\Seeder;

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
            HariLibur::updateOrCreate(
                ['tanggal' => $hariLibur['tanggal']],
                $hariLibur,
            );
        }
    }
}
