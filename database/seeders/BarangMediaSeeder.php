<?php

namespace Database\Seeders;

use App\Models\Barang;
use App\Models\DokumenBarang;
use App\Models\FotoBarang;
use App\Services\TransactionalFileStorage;
use Database\Seeders\Support\DemoMedia;
use Illuminate\Database\Seeder;

class BarangMediaSeeder extends Seeder
{
    private TransactionalFileStorage $files;

    public function run(): void
    {
        $this->files = app(TransactionalFileStorage::class);
        $colors = ['#1e40af', '#166534', '#6b21a8', '#9a3412', '#9f1239', '#115e59'];

        foreach (Barang::orderBy('kode_barang')->get() as $index => $barang) {
            if ($index % 5 !== 0) {
                $path = "demo/barang/{$barang->kode_barang}.svg";
                $this->files->put('public', $path, DemoMedia::svg($barang->nama_barang, $colors[$index % count($colors)]));
                $barang->update(['foto_sampul' => $path]);
            }

            if ($index % 2 === 0) {
                $path = "demo/barang/{$barang->kode_barang}-pendukung.svg";
                $this->files->put('public', $path, DemoMedia::svg('Foto Pendukung', $colors[($index + 1) % count($colors)]));
                FotoBarang::updateOrCreate(
                    ['barang_id' => $barang->id, 'path' => $path],
                    ['keterangan' => 'Foto pendukung data demo'],
                );
            }

            if ($index % 4 !== 0) {
                $this->simpanDokumen($barang, 'Nota Pembelian', "nota-{$barang->kode_barang}.pdf");
            }

            if ($index % 3 === 0) {
                $this->simpanDokumen($barang, 'Kartu Garansi', "garansi-{$barang->kode_barang}.pdf");
            }
        }
    }

    private function simpanDokumen(Barang $barang, string $jenis, string $nama): void
    {
        $path = "demo/dokumen-barang/{$nama}";
        $this->files->put('local', $path, DemoMedia::pdf("{$jenis} - {$barang->nama_barang}"));
        DokumenBarang::updateOrCreate(
            ['barang_id' => $barang->id, 'jenis_dokumen' => $jenis],
            ['nama_asli' => $nama, 'path' => $path],
        );
    }
}
