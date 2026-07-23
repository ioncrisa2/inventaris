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

        // Nama barang per kelompok item, sekaligus rentang harga wajar (min-max) dan
        // golongan penyusutannya. Golongan mengikuti pengelompokan harta berwujud
        // bukan bangunan (Kelompok 1-4); tidak ada item contoh untuk golongan
        // Bangunan karena katalog demo ini hanya barang bergerak.
        $katalog = [
            ['golongan' => 'Bukan Bangunan - Kelompok 1', 'range' => [1000000, 15000000], 'items' => ['AC Split 1 PK', 'Kulkas Kantor Mini', 'Televisi LED 43 Inci', 'Mesin Fotokopi', 'Dispenser Air Panas Dingin', 'Kipas Angin Berdiri']],
            ['golongan' => 'Bukan Bangunan - Kelompok 1', 'range' => [5000000, 20000000], 'items' => ['Laptop Lenovo ThinkPad', 'Laptop Asus VivoBook', 'PC Desktop Rakitan', 'Laptop Dell Latitude', 'Laptop HP ProBook', 'Monitor LED 24 Inci']],
            ['golongan' => 'Bukan Bangunan - Kelompok 1', 'range' => [2000000, 6000000], 'items' => ['Printer Epson L3210', 'Printer Canon Pixma', 'Scanner Dokumen Fujitsu']],
            ['golongan' => 'Bukan Bangunan - Kelompok 1', 'range' => [1000000, 8000000], 'items' => ['Router Mikrotik', 'Switch Hub 24 Port', 'Access Point Ubiquiti']],
            ['golongan' => 'Bukan Bangunan - Kelompok 1', 'range' => [2000000, 10000000], 'items' => ['Mesin Absensi Fingerprint', 'Telepon Kantor PABX', 'Mesin Penghancur Kertas', 'Mesin Hitung Uang']],
            ['golongan' => 'Bukan Bangunan - Kelompok 1', 'range' => [500000, 8000000], 'items' => ['Meja Kerja Staff', 'Kursi Kantor Ergonomis', 'Lemari Arsip Besi', 'Sofa Ruang Tamu', 'Meja Rapat Besar', 'Rak Buku Kayu']],
            ['golongan' => 'Bukan Bangunan - Kelompok 2', 'range' => [20000000, 250000000], 'items' => ['Mobil Operasional Avanza', 'Motor Dinas Honda Beat', 'Truk Angkut Barang']],
            ['golongan' => 'Bukan Bangunan - Kelompok 1', 'range' => [500000, 5000000], 'items' => ['Vacuum Cleaner Industrial', 'Mesin Poles Lantai', 'Trolley Kebersihan']],
            ['golongan' => 'Bukan Bangunan - Kelompok 1', 'range' => [200000, 1500000], 'items' => ['Paket ATK Ruang IT', 'Paket ATK Ruang Keuangan', 'Paket ATK Ruang SDM']],
            ['golongan' => 'Bukan Bangunan - Kelompok 3', 'range' => [3000000, 30000000], 'items' => ['Genset 5000 Watt', 'UPS Server', 'Panel Listrik Cadangan']],
            ['golongan' => 'Bukan Bangunan - Kelompok 1', 'range' => [500000, 5000000], 'items' => ['Kotak P3K Lengkap', 'Tabung Oksigen Portable']],
            ['golongan' => 'Bukan Bangunan - Kelompok 1', 'range' => [1000000, 15000000], 'items' => ['Lisensi Microsoft Office', 'Lisensi Antivirus Kaspersky', 'Lisensi Aplikasi Akuntansi']],
            ['golongan' => 'Bukan Bangunan - Kelompok 1', 'range' => [1000000, 10000000], 'items' => ['AC Split Ruang Rapat', 'Karpet Ruang Tamu', 'Papan Tulis Whiteboard']],
            ['golongan' => 'Bukan Bangunan - Kelompok 1', 'range' => [3000000, 20000000], 'items' => ['Sound System Aula', 'Kamera CCTV Set']],
            ['golongan' => 'Bukan Bangunan - Kelompok 3', 'range' => [10000000, 100000000], 'items' => ['Generator Cadangan Gedung', 'Pompa Air Gedung']],
        ];

        $flattened = [];
        foreach ($katalog as $kelompok) {
            foreach ($kelompok['items'] as $namaBarang) {
                $flattened[] = [
                    'kategori' => $kelompok['golongan'],
                    'nama_barang' => $namaBarang,
                    'range' => $kelompok['range'],
                ];
            }
        }

        // Batasi/genapkan tepat 50 barang secara deterministik.
        $target = 50;
        $baseCount = count($flattened);
        for ($i = 0; count($flattened) < $target; $i++) {
            $flattened[] = $flattened[$i % $baseCount];
        }
        $flattened = array_slice($flattened, 0, $target);

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
                    'unit_kerja_id' => $unitKerjaId,
                    'tanggal_perolehan' => $tanggalPerolehan,
                    'harga_perolehan' => $harga,
                ]
            );
        }
    }
}
