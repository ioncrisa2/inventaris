<?php

use App\Models\Barang;
use App\Models\UnitKerja;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(adminUser());
    $this->unitKerja = UnitKerja::create([
        'nama_unit' => 'Teknologi Informasi',
    ]);
});

test('halaman memakai locale Indonesia dan response UTF-8', function () {
    $response = $this->get(route('unit-kerja.index'));

    $response->assertOk()
        ->assertSee('<html lang="id-ID">', false);

    expect(strtolower((string) $response->headers->get('content-type')))
        ->toContain('charset=utf-8')
        ->and(config('database.connections.mysql.charset'))->toBe('utf8mb4')
        ->and(config('database.connections.mysql.collation'))->toBe('utf8mb4_unicode_ci')
        ->and(config('database.connections.mariadb.charset'))->toBe('utf8mb4')
        ->and(config('database.connections.mariadb.collation'))->toBe('utf8mb4_unicode_ci');
});

test('pesan konfirmasi hapus tidak mengalami double escaping', function () {
    $this->unitKerja->update(['nama_unit' => 'IT']);

    $this->get(route('unit-kerja.index'))
        ->assertOk()
        ->assertSee('data-delete-message="Hapus unit kerja &quot;IT&quot;?', false)
        ->assertDontSee('&amp;quot;', false);
});

test('form inventaris melokalkan tanggal nominal dan file upload', function () {
    $barang = Barang::create([
        'kode_barang' => 'INV-001',
        'nama_barang' => 'Laptop Operasional',
        'kategori' => 'Bukan Bangunan - Kelompok 1',
        'unit_kerja_id' => $this->unitKerja->id,
        'tanggal_perolehan' => '2026-07-23',
        'harga_perolehan' => 1000000,
    ]);

    $this->get(route('barang.edit', $barang))
        ->assertOk()
        ->assertSee('lang="id-ID"', false)
        ->assertSee('placeholder="dd/mm/yyyy"', false)
        ->assertSee('data-local-date', false)
        ->assertSee('data-money-input', false)
        ->assertSee('value="1.000.000"', false)
        ->assertSee('Pilih file')
        ->assertSee('Belum ada file dipilih')
        ->assertDontSee('Choose file');
});

test('document repeater shows its upload constraint once above all rows', function () {
    $response = $this->get(route('barang.create'))->assertOk();

    expect(substr_count($response->getContent(), 'PDF/JPG/PNG, maks. 5MB.'))->toBe(1);
});

test('halaman cetak barcode dan QR memakai ukuran label ringkas', function () {
    $barang = Barang::create([
        'kode_barang' => 'INV-001',
        'nama_barang' => 'Laptop Operasional',
        'kategori' => 'Bukan Bangunan - Kelompok 1',
        'unit_kerja_id' => $this->unitKerja->id,
        'tanggal_perolehan' => '2026-07-23',
        'harga_perolehan' => 1000000,
    ]);

    $this->get(route('barang.barcode', $barang))
        ->assertOk()
        ->assertSee('page-layout--barcode-label', false)
        ->assertSee('screen-actions no-print', false);

    $this->get(route('barang.qr-code', $barang))
        ->assertOk()
        ->assertSee('page-layout--qr-label', false)
        ->assertSee('screen-actions no-print', false);
});

test('pagination menyediakan label Indonesia', function () {
    expect(__('pagination.previous'))->toContain('Sebelumnya')
        ->and(__('pagination.next'))->toContain('Berikutnya')
        ->and(__('Showing'))->toBe('Menampilkan')
        ->and(__('results'))->toBe('hasil');
});

test('halaman daftar tidak mengulang eyebrow dan judul card tabel', function () {
    $this->get(route('unit-kerja.index'))
        ->assertOk()
        ->assertSee('<h1>Unit Kerja</h1>', false)
        ->assertSee('Cari unit kerja...')
        ->assertDontSee('page-header__eyebrow', false)
        ->assertDontSee('data-table-card__heading', false)
        ->assertDontSee('Daftar Unit Kerja');
});

test('filter daftar bekerja otomatis dan menampilkan chip filter aktif', function () {
    $this->get(route('unit-kerja.index', ['search' => 'Teknologi']))
        ->assertOk()
        ->assertSee('data-filter-form', false)
        ->assertSee('data-active-filter-list', false)
        ->assertSee('Reset semua')
        ->assertDontSee('btn btn-outline-primary', false);
});

test('unit yang masih dipakai menjelaskan dependensi sebelum penghapusan', function () {
    Barang::create([
        'kode_barang' => 'INV-DEPENDENSI',
        'nama_barang' => 'Laptop Unit',
        'kategori' => 'Bukan Bangunan - Kelompok 1',
        'unit_kerja_id' => $this->unitKerja->id,
        'tanggal_perolehan' => '2026-07-23',
        'harga_perolehan' => 1000000,
    ]);

    $this->get(route('unit-kerja.index'))
        ->assertOk()
        ->assertSee('data-bulk-blocked-message=', false)
        ->assertSee('data-delete-blocked-message=', false)
        ->assertSee('masih dipakai oleh 1 barang');
});

test('tabel inventaris memakai label golongan singkat dan warna kondisi khusus', function () {
    $barang = Barang::create([
        'kode_barang' => 'INV-002',
        'nama_barang' => 'Meja Kerja',
        'kategori' => 'Bukan Bangunan - Kelompok 1',
        'unit_kerja_id' => $this->unitKerja->id,
        'tanggal_perolehan' => '2026-07-23',
        'harga_perolehan' => 1000000,
    ]);

    $barang->riwayatKondisi()->create([
        'tanggal_pemeriksaan' => '2026-07-23',
        'kondisi' => 'Baru',
    ]);

    $this->get(route('barang.index'))
        ->assertOk()
        ->assertSee('Kel. 1')
        ->assertSee('condition-badge--new', false)
        ->assertSee('title="Bukan Bangunan - Kelompok 1"', false);

    expect(array_unique(config('inventaris.kondisi_warna')))
        ->toHaveCount(count(config('inventaris.kondisi_warna')));
});

test('halaman form tidak lagi menampilkan label formulir data', function () {
    $this->get(route('barang.create'))
        ->assertOk()
        ->assertSee('<h1>Tambah Barang</h1>', false)
        ->assertSee('Data Inventaris')
        ->assertDontSee('Formulir Data')
        ->assertDontSee('page-header__eyebrow', false);
});

test('halaman detail barang memakai judul dan subtitle kontekstual', function () {
    $barang = Barang::create([
        'kode_barang' => 'INV-001',
        'nama_barang' => 'Laptop Operasional',
        'kategori' => 'Bukan Bangunan - Kelompok 1',
        'unit_kerja_id' => $this->unitKerja->id,
        'tanggal_perolehan' => '2026-07-23',
        'harga_perolehan' => 1000000,
    ]);

    $this->get(route('barang.show', $barang))
        ->assertOk()
        ->assertSee('<h1>Laptop Operasional</h1>', false)
        ->assertSee('INV-001 — Teknologi Informasi')
        ->assertSee('Riwayat Kondisi')
        ->assertDontSee('Informasi inventaris dan riwayat pemeriksaan kondisi.');
});
