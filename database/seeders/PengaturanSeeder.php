<?php

namespace Database\Seeders;

use App\Models\Pengaturan;
use App\Services\KodeBarangGenerator;
use Illuminate\Database\Seeder;

class PengaturanSeeder extends Seeder
{
    public function run(): void
    {
        Pengaturan::set('format_kode_barang', KodeBarangGenerator::DEFAULT_TEMPLATE);
        Pengaturan::set('digit_nomor_urut', (string) KodeBarangGenerator::DEFAULT_SEQUENCE_DIGITS);
    }
}
