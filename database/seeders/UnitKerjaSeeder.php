<?php

namespace Database\Seeders;

use App\Models\UnitKerja;
use Illuminate\Database\Seeder;

class UnitKerjaSeeder extends Seeder
{
    public function run(): void
    {
        $unitKerjas = [
            ['nama_unit' => 'IT', 'kode' => 'IT'],
            ['nama_unit' => 'Keuangan', 'kode' => 'KEU'],
            ['nama_unit' => 'SDM', 'kode' => 'SDM'],
            ['nama_unit' => 'Operasional', 'kode' => 'OPS'],
            ['nama_unit' => 'Bag. Umum', 'kode' => 'UMU'],
            ['nama_unit' => 'Logistik', 'kode' => 'LOG'],
        ];

        foreach ($unitKerjas as $unitKerja) {
            UnitKerja::updateOrCreate(
                ['nama_unit' => $unitKerja['nama_unit']],
                $unitKerja,
            );
        }
    }
}
