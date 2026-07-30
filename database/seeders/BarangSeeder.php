<?php

namespace Database\Seeders;

use App\Models\Barang;
use App\Models\UnitKerja;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

class BarangSeeder extends Seeder
{
    public function run(): void
    {
        $unitKerjaIds = UnitKerja::orderBy('nama_unit')->pluck('id')->all();

        $katalog = config('kategori_penyusutan.item_per_golongan');
        $golongan = array_keys($katalog);
        $rentangHarga = [
            'Bukan Bangunan - Kelompok 1' => [500000, 25000000],
            'Bukan Bangunan - Kelompok 2' => [2000000, 350000000],
            'Bukan Bangunan - Kelompok 3' => [5000000, 500000000],
            'Bukan Bangunan - Kelompok 4' => [500000000, 5000000000],
            'Bangunan - Permanen' => [250000000, 10000000000],
            'Bangunan - Bukan Permanen' => [25000000, 500000000],
        ];

        // Tepat 50 barang demo, disebar ke seluruh golongan dan selalu memakai
        // pasangan jenis-golongan yang berasal dari katalog tervalidasi.
        $target = 50;
        $flattened = [];

        for ($index = 0; $index < $target; $index++) {
            $kategori = $golongan[$index % count($golongan)];
            $urutanDalamGolongan = intdiv($index, count($golongan));
            $jenisBarang = $katalog[$kategori][$urutanDalamGolongan % count($katalog[$kategori])];

            $flattened[] = [
                'kategori' => $kategori,
                'jenis_barang' => $jenisBarang,
                'nama_barang' => $jenisBarang.' '.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT),
                'range' => $rentangHarga[$kategori],
            ];
        }

        $tanggalDasar = CarbonImmutable::create(2022, 1, 10);

        foreach ($flattened as $index => $item) {
            $nomor = $index + 1;
            [$hargaMin, $hargaMax] = $item['range'];
            $unitKerjaId = $unitKerjaIds[$index % count($unitKerjaIds)];
            $tanggalPerolehan = $tanggalDasar->addDays($index * 23)->toDateString();
            $jumlahLangkah = max(1, intdiv($hargaMax - $hargaMin, 50000));
            $harga = $hargaMin + (($index * 17) % ($jumlahLangkah + 1)) * 50000;

            Barang::updateOrCreate(
                ['kode_barang' => 'BRG-'.str_pad((string) $nomor, 3, '0', STR_PAD_LEFT)],
                [
                    'nama_barang' => $item['nama_barang'],
                    'kategori' => $item['kategori'],
                    'jenis_barang' => $item['jenis_barang'],
                    'unit_kerja_id' => $unitKerjaId,
                    'tanggal_perolehan' => $tanggalPerolehan,
                    'harga_perolehan' => $harga,
                ]
            );
        }
    }
}
