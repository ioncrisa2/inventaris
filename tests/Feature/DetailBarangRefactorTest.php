<?php

use App\Models\Barang;
use App\Models\DokumenBarang;
use App\Models\FotoBarang;
use App\Models\RiwayatKondisiBarang;
use App\Models\UnitKerja;
use App\Support\PenyusutanCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(adminUser());
    $this->unitKerja = UnitKerja::create(['nama_unit' => 'SDM']);
});

test('detail barang dengan aset masa manfaat panjang 20 tahun menampilkan kartu ringkasan dan tabel windowing', function () {
    // Gedung 20 tahun, perolehan Nov 2020, harga 49.597.750
    $barang = Barang::create([
        'kode_barang' => 'SDM02-BGP-2020-0140',
        'nama_barang' => 'GEDUNG KANTOR PUSAT',
        'kategori' => 'Bangunan - Permanen',
        'jenis_barang' => 'Gedung kantor permanen',
        'unit_kerja_id' => $this->unitKerja->id,
        'lokasi_penempatan' => 'Kantor Pusat',
        'keterangan_lokasi' => 'Kantor Pusat', // sama dengan lokasi, harus disembunyikan
        'tanggal_perolehan' => '2020-11-05',
        'harga_perolehan' => 49597750,
    ]);

    RiwayatKondisiBarang::create([
        'barang_id' => $barang->id,
        'tanggal_pemeriksaan' => '2020-11-05',
        'kondisi' => 'Baik',
        'keterangan' => 'Kondisi awal gedung',
        'biaya_perbaikan' => null,
    ]);

    $response = $this->get(route('barang.show', $barang));
    $response->assertOk()
        ->assertSee('GEDUNG KANTOR PUSAT')
        ->assertSee('SDM02-BGP-2020-0140')
        ->assertSee('Bangunan - Permanen')
        // Kartu Ringkasan
        ->assertSee('Nilai Perolehan')
        ->assertSee('Rp 49.597.750')
        ->assertSee('Nilai Buku')
        ->assertSee('Sisa Masa Manfaat')
        ->assertSee('Kelengkapan Data')
        // Tab
        ->assertSee('Informasi')
        ->assertSee('Penyusutan')
        ->assertSee('Riwayat Kondisi')
        ->assertSee('Lampiran')
        // Header Dropdown Cetak & Edit
        ->assertSee('Cetak Barcode')
        ->assertSee('Cetak QR Code')
        ->assertSee('Edit Data')
        // Windowing tabel penyusutan
        ->assertSee('data-chart-penyusutan', false)
        ->assertSee('pro-rata 2 bulan');

    // Pastikan perhitungan penyusutan balancing ke 0 di tahun akhir
    $jadwal = PenyusutanCalculator::jadwalTahunan('Bangunan - Permanen', '49597750', $barang->tanggal_perolehan);
    expect($jadwal[2040]['nilai_buku_akhir_tahun'])->toBe('0.00')
        ->and($jadwal[2020]['penyusutan_tahun_ini'])->toBe('413315.00')
        ->and($jadwal[2021]['akumulasi_akhir_tahun'])->toBe('2893203.00');
});

test('detail barang dengan aset masa manfaat pendek 4 tahun', function () {
    $barang = Barang::create([
        'kode_barang' => 'SDM-KL1-2023-0001',
        'nama_barang' => 'Laptop ThinkPad T14',
        'kategori' => 'Bukan Bangunan - Kelompok 1',
        'jenis_barang' => 'Laptop / notebook',
        'unit_kerja_id' => $this->unitKerja->id,
        'lokasi_penempatan' => 'Kantor Pusat',
        'tanggal_perolehan' => '2023-01-10',
        'harga_perolehan' => 16000000,
    ]);

    $response = $this->get(route('barang.show', $barang));
    $response->assertOk()
        ->assertSee('Laptop ThinkPad T14')
        ->assertSee('4 tahun')
        ->assertSee('Garis Lurus')
        ->assertSee('Rp 16.000.000');
});

test('detail barang tanpa foto dan dokumen menampilkan placeholder dan empty state konsisten', function () {
    $barang = Barang::create([
        'kode_barang' => 'SDM-KL2-2024-0002',
        'nama_barang' => 'AC Split Ruang Rapat',
        'kategori' => 'Bukan Bangunan - Kelompok 2',
        'jenis_barang' => 'AC split',
        'unit_kerja_id' => $this->unitKerja->id,
        'lokasi_penempatan' => 'Kantor Pusat',
        'tanggal_perolehan' => '2024-06-01',
        'harga_perolehan' => 6500000,
    ]);

    $response = $this->get(route('barang.show', $barang));
    $response->assertOk()
        ->assertSee('asset-thumbnail--placeholder', false)
        ->assertSee('Belum ada foto pendukung')
        ->assertSee('Belum ada dokumen')
        ->assertSee('Checklist Dokumen Disarankan');
});

test('detail barang dengan riwayat kondisi lebih dari 5 entri menghitung total biaya perbaikan', function () {
    $barang = Barang::create([
        'kode_barang' => 'SDM-KL1-2022-0003',
        'nama_barang' => 'Printer Server',
        'kategori' => 'Bukan Bangunan - Kelompok 1',
        'jenis_barang' => 'Printer laser',
        'unit_kerja_id' => $this->unitKerja->id,
        'lokasi_penempatan' => 'Kantor Pusat',
        'tanggal_perolehan' => '2022-01-01',
        'harga_perolehan' => 8000000,
    ]);

    $biayaList = [100000, 150000, 200000, 250000, 300000, 350000]; // Total = 1.350.000
    foreach ($biayaList as $idx => $biaya) {
        RiwayatKondisiBarang::create([
            'barang_id' => $barang->id,
            'tanggal_pemeriksaan' => "2022-0" . ($idx + 2) . "-01",
            'kondisi' => 'Perlu Perawatan',
            'keterangan' => "Perawatan rutin ke-" . ($idx + 1),
            'biaya_perbaikan' => $biaya,
        ]);
    }

    $response = $this->get(route('barang.show', $barang));
    $response->assertOk()
        ->assertSee('Total Biaya Perbaikan:')
        ->assertSee('Rp 1.350.000')
        ->assertSee('Perawatan rutin ke-1')
        ->assertSee('Perawatan rutin ke-6');
});

