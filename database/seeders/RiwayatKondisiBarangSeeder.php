<?php

namespace Database\Seeders;

use App\Models\Barang;
use App\Models\RiwayatKondisiBarang;
use Illuminate\Database\Seeder;

class RiwayatKondisiBarangSeeder extends Seeder
{
    private const KONDISI = [
        'Baru',
        'Sangat Baik',
        'Baik',
        'Baik',
        'Cukup Baik',
        'Perlu Perawatan',
        'Rusak Ringan',
        'Rusak Berat',
    ];

    public function run(): void
    {
        $barangs = Barang::orderBy('kode_barang')->get();

        foreach ($barangs as $index => $barang) {
            $kondisi = self::KONDISI[$index % count(self::KONDISI)];

            $tanggalPerolehan = $barang->tanggal_perolehan->toImmutable();
            $tanggalPemeriksaan = $tanggalPerolehan->addDays(30 + ($index % 90));

            if ($tanggalPemeriksaan->isFuture()) {
                $tanggalPemeriksaan = now()->toImmutable();
            }

            $butuhBiayaPerbaikan = in_array($kondisi, ['Rusak Ringan', 'Rusak Berat'], true);

            RiwayatKondisiBarang::updateOrCreate(
                [
                    'barang_id' => $barang->id,
                    'keterangan' => 'Pemeriksaan rutin otomatis',
                ],
                [
                    'tanggal_pemeriksaan' => $tanggalPemeriksaan->toDateString(),
                    'kondisi' => $kondisi,
                    'biaya_perbaikan' => $butuhBiayaPerbaikan
                        ? 250000 + ($index % 12) * 175000
                        : null,
                ],
            );
        }
    }
}
