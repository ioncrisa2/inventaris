<?php

use App\Models\Barang;
use App\Models\UnitKerja;
use App\Services\KodeBarangGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('admin can view and update pengaturan', function () {
    $this->actingAs(adminUser());

    $this->get(route('pengaturan.edit'))
        ->assertOk()
        ->assertSee('Format Penomoran Inventaris')
        ->assertSee('Jumlah digit nomor urut');

    $this->put(route('pengaturan.update'), [
        'format_kode_barang' => '{KATEGORI}.{UNIT}.{TAHUN}.{URUT}',
        'digit_nomor_urut' => 5,
    ])->assertRedirect(route('pengaturan.edit'));

    $this->assertDatabaseHas('pengaturan', [
        'key' => 'format_kode_barang',
        'value' => '{KATEGORI}.{UNIT}.{TAHUN}.{URUT}',
    ]);

    $this->assertDatabaseHas('pengaturan', [
        'key' => 'digit_nomor_urut',
        'value' => '5',
    ]);
});

test('staff can access appearance settings but cannot manage inventory numbering', function () {
    $this->actingAs(staffUser());

    $this->get(route('pengaturan.edit'))
        ->assertOk()
        ->assertSee('Tampilan Aplikasi')
        ->assertDontSee('Format Penomoran Inventaris');

    $this->put(route('pengaturan.update'), [
        'format_kode_barang' => 'INV-{URUT}',
        'digit_nomor_urut' => 4,
    ])->assertForbidden();
});

test('pengaturan rejects unknown tokens', function () {
    $this->actingAs(adminUser());

    $this->put(route('pengaturan.update'), [
        'format_kode_barang' => 'INV-{TIDAK_DIKENAL}-{URUT}',
        'digit_nomor_urut' => 4,
    ])->assertSessionHasErrors('format_kode_barang');
});

test('pengaturan requires the sequence token to preserve unique codes', function () {
    $this->actingAs(adminUser());

    $this->put(route('pengaturan.update'), [
        'format_kode_barang' => 'INV-{TAHUN}',
        'digit_nomor_urut' => 4,
    ])->assertSessionHasErrors('format_kode_barang');
});

test('kode barang generator produces the expected pattern from the default template', function () {
    $generator = app(KodeBarangGenerator::class);
    $unitKerja = UnitKerja::create(['nama_unit' => 'Teknologi Informasi', 'kode' => 'IT']);

    $kode = $generator->generate('Bukan Bangunan - Kelompok 1', $unitKerja->id, '2026-07-21');

    expect($kode)->toBe('IT-KL1-2026-0001');
});

test('kode barang generator falls back to a derived unit code when kode is blank', function () {
    $generator = app(KodeBarangGenerator::class);
    $unitKerja = UnitKerja::create(['nama_unit' => 'Sumber Daya Manusia']);

    $kode = $generator->generate('Bukan Bangunan - Kelompok 2', $unitKerja->id, '2026-07-21');

    expect($kode)->toBe('SUM-KL2-2026-0001');
});

test('kode barang generator increments sequence and avoids collisions', function () {
    $generator = app(KodeBarangGenerator::class);
    $unitKerja = UnitKerja::create(['nama_unit' => 'IT', 'kode' => 'IT']);

    $kode1 = $generator->generate('Bukan Bangunan - Kelompok 1', $unitKerja->id, '2026-07-21');

    Barang::create([
        'kode_barang' => $kode1,
        'nama_barang' => 'Laptop',
        'kategori' => 'Bukan Bangunan - Kelompok 1',
        'unit_kerja_id' => $unitKerja->id,
        'tanggal_perolehan' => '2026-07-21',
        'harga_perolehan' => 1000000,
    ]);

    $kode2 = $generator->generate('Bukan Bangunan - Kelompok 1', $unitKerja->id, '2026-07-21');

    expect($kode2)->not->toBe($kode1);
});

test('kode barang generator respects the configured sequence digit count', function () {
    $generator = app(KodeBarangGenerator::class);
    $unitKerja = UnitKerja::create(['nama_unit' => 'IT', 'kode' => 'IT']);

    $generator->simpanPengaturan('INV-{URUT}', 6);

    expect($generator->generate('Bukan Bangunan - Kelompok 1', $unitKerja->id, '2026-07-21'))
        ->toBe('INV-000001');
});
