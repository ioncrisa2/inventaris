<?php

use App\Models\Barang;
use App\Models\DokumenBarang;
use App\Models\FotoBarang;
use App\Models\RiwayatKondisiBarang;
use App\Models\UnitKerja;
use App\Support\KelengkapanBarangCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('kelengkapan barang menghitung persentase dan daftar item belum lengkap secara akurat', function () {
    $unit = UnitKerja::create(['nama_unit' => 'SDM']);

    // Barang baru tanpa foto, tanpa kondisi, tanpa dokumen
    $barang = Barang::create([
        'kode_barang' => 'SDM02-BGP-2020-0140',
        'nama_barang' => 'GEDUNG KANTOR PUSAT',
        'kategori' => 'Bangunan - Permanen',
        'jenis_barang' => 'Gedung kantor permanen',
        'unit_kerja_id' => $unit->id,
        'lokasi_penempatan' => 'Kantor Pusat',
        'tanggal_perolehan' => '2020-11-05',
        'harga_perolehan' => 49597750,
    ]);

    // Bangunan permanen butuh 4 dokumen (Sertifikat, PBG/IMB, BAST, Kontrak/Faktur) + identitas + kondisi + foto = total 7 kriteria
    $hasilAwal = KelengkapanBarangCalculator::hitung($barang);
    expect($hasilAwal['is_lengkap'])->toBeFalse()
        ->and($hasilAwal['total_kriteria'])->toBe(7)
        ->and($hasilAwal['total_terpenuhi'])->toBe(1) // Hanya identitas
        ->and($hasilAwal['persentase'])->toBe(14) // 1/7 = 14%
        ->and($hasilAwal['item_belum_lengkap'])->toContain('Foto fisik aset')
        ->and($hasilAwal['item_belum_lengkap'])->toContain('Pencatatan kondisi fisik')
        ->and($hasilAwal['item_belum_lengkap'])->toContain('Dokumen Sertifikat');

    // Tambah riwayat kondisi
    RiwayatKondisiBarang::create([
        'barang_id' => $barang->id,
        'tanggal_pemeriksaan' => '2020-11-05',
        'kondisi' => 'Baik',
    ]);
    // Tambah foto pendukung
    FotoBarang::create([
        'barang_id' => $barang->id,
        'path' => 'demo/gedung.jpg',
    ]);

    $barang->load('kondisiTerakhir', 'fotoPendukung', 'dokumen');
    $hasilSetelahKondisiFoto = KelengkapanBarangCalculator::hitung($barang);
    expect($hasilSetelahKondisiFoto['total_terpenuhi'])->toBe(3) // identitas + kondisi + foto = 3/7 = 43%
        ->and($hasilSetelahKondisiFoto['persentase'])->toBe(43);

    // Lengkapi semua 4 dokumen
    foreach (config('inventaris.dokumen_wajib_per_golongan.Bangunan - Permanen') as $doc) {
        DokumenBarang::create([
            'barang_id' => $barang->id,
            'jenis_dokumen' => $doc,
            'nama_asli' => strtolower($doc).'.pdf',
            'path' => 'dokumen/'.strtolower($doc).'.pdf',
        ]);
    }

    $barang->load('kondisiTerakhir', 'fotoPendukung', 'dokumen');
    $hasilLengkap = KelengkapanBarangCalculator::hitung($barang);
    expect($hasilLengkap['is_lengkap'])->toBeTrue()
        ->and($hasilLengkap['total_terpenuhi'])->toBe(7)
        ->and($hasilLengkap['persentase'])->toBe(100)
        ->and($hasilLengkap['item_belum_lengkap'])->toBeEmpty();
});
