<?php

use App\Models\Barang;
use App\Models\Karyawan;
use App\Models\Koperasi;
use App\Models\TransaksiGaji;
use App\Models\UnitKerja;
use App\Repositories\LaporanRepository;
use App\Services\DashboardService;
use App\Services\KodeBarangGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('dashboard inventory summary does not leak across koperasi even when cached', function () {
    $koperasiA = Koperasi::create(['nama' => 'Koperasi A']);
    $koperasiB = Koperasi::create(['nama' => 'Koperasi B']);
    $userA = adminPrimerUser($koperasiA);
    $userB = adminPrimerUser($koperasiB);

    $this->actingAs($userA);
    $unitA = UnitKerja::create(['nama_unit' => 'Unit A']);
    Barang::create([
        'kode_barang' => 'A-0001',
        'nama_barang' => 'Laptop A',
        'kategori' => 'Elektronik',
        'unit_kerja_id' => $unitA->id,
        'tanggal_perolehan' => '2026-01-01',
        'harga_perolehan' => 5000000,
    ]);

    $this->actingAs($userB);
    $unitB = UnitKerja::create(['nama_unit' => 'Unit B']);
    Barang::create([
        'kode_barang' => 'B-0001',
        'nama_barang' => 'Laptop B1',
        'kategori' => 'Elektronik',
        'unit_kerja_id' => $unitB->id,
        'tanggal_perolehan' => '2026-01-01',
        'harga_perolehan' => 1000000,
    ]);
    Barang::create([
        'kode_barang' => 'B-0002',
        'nama_barang' => 'Laptop B2',
        'kategori' => 'Elektronik',
        'unit_kerja_id' => $unitB->id,
        'tanggal_perolehan' => '2026-01-01',
        'harga_perolehan' => 1000000,
    ]);

    // Muat dashboard koperasi A dulu (mengisi cache), LALU koperasi B — kalau
    // cache key dashboard tidak dipisah per tenant, koperasi B akan melihat
    // angka milik koperasi A yang sudah ke-cache duluan, bukan datanya sendiri.
    $this->actingAs($userA);
    expect(app(DashboardService::class)->widgets($userA)['totalBarang'])->toBe(1);

    $this->actingAs($userB);
    expect(app(DashboardService::class)->widgets($userB)['totalBarang'])->toBe(2);

    // Dan sebaliknya: setelah cache koperasi B terisi, koperasi A tetap
    // melihat datanya sendiri, bukan cache B.
    $this->actingAs($userA);
    expect(app(DashboardService::class)->widgets($userA)['totalBarang'])->toBe(1);
});

test('format kode barang setting is independent per koperasi', function () {
    $koperasiA = Koperasi::create(['nama' => 'Koperasi A']);
    $koperasiB = Koperasi::create(['nama' => 'Koperasi B']);
    $userA = adminPrimerUser($koperasiA);
    $userB = adminPrimerUser($koperasiB);

    $this->actingAs($userA);
    $unitA = UnitKerja::create(['nama_unit' => 'IT', 'kode' => 'IT']);
    app(KodeBarangGenerator::class)->simpanPengaturan('A-{URUT}', 4);

    $this->actingAs($userB);
    $unitB = UnitKerja::create(['nama_unit' => 'IT', 'kode' => 'IT']);
    app(KodeBarangGenerator::class)->simpanPengaturan('B-{URUT}', 4);

    $this->actingAs($userA);
    expect(app(KodeBarangGenerator::class)->generate('Elektronik', $unitA->id, '2026-01-01'))
        ->toBe('A-0001');

    $this->actingAs($userB);
    expect(app(KodeBarangGenerator::class)->generate('Elektronik', $unitB->id, '2026-01-01'))
        ->toBe('B-0001');
});

test('barang of one koperasi is completely invisible to another koperasi via route model binding', function () {
    $koperasiA = Koperasi::create(['nama' => 'Koperasi A']);
    $koperasiB = Koperasi::create(['nama' => 'Koperasi B']);
    $userA = adminPrimerUser($koperasiA);
    $userB = adminPrimerUser($koperasiB);

    $this->actingAs($userA);
    $unitA = UnitKerja::create(['nama_unit' => 'Unit A']);
    $barangA = Barang::create([
        'kode_barang' => 'A-0001',
        'nama_barang' => 'Laptop A',
        'kategori' => 'Elektronik',
        'unit_kerja_id' => $unitA->id,
        'tanggal_perolehan' => '2026-01-01',
        'harga_perolehan' => 5000000,
    ]);

    // Koperasi B tidak boleh sama sekali bisa melihat/menghapus barang milik
    // koperasi A lewat route model binding biasa (barang.koperasi_id ikut
    // di-scope via BelongsToKoperasi).
    $this->actingAs($userB);
    $this->get(route('barang.show', $barangA))->assertNotFound();
    $this->delete(route('barang.destroy', $barangA))->assertNotFound();
});

test('payroll recap per unit kerja does not leak transaksi gaji across koperasi', function () {
    $koperasiA = Koperasi::create(['nama' => 'Koperasi A']);
    $koperasiB = Koperasi::create(['nama' => 'Koperasi B']);
    $userA = adminPrimerUser($koperasiA);
    $userB = adminPrimerUser($koperasiB);

    $this->actingAs($userA);
    $unitA = UnitKerja::create(['nama_unit' => 'Unit A']);
    $karyawanA = Karyawan::create([
        'nik' => 'A-001', 'nama_lengkap' => 'Karyawan A', 'tanggal_lahir' => '1990-01-01',
        'unit_kerja_id' => $unitA->id, 'jabatan' => 'Staf', 'status_karyawan' => 'PKWTT', 'gaji_pokok' => 5000000,
    ]);
    TransaksiGaji::create([
        'karyawan_id' => $karyawanA->id, 'bulan' => 7, 'tahun' => 2026,
        'gaji_pokok' => 5000000, 'gaji_bersih' => 5000000,
    ]);

    $this->actingAs($userB);
    $unitB = UnitKerja::create(['nama_unit' => 'Unit B']);
    $karyawanB = Karyawan::create([
        'nik' => 'B-001', 'nama_lengkap' => 'Karyawan B', 'tanggal_lahir' => '1990-01-01',
        'unit_kerja_id' => $unitB->id, 'jabatan' => 'Staf', 'status_karyawan' => 'PKWTT', 'gaji_pokok' => 3000000,
    ]);
    TransaksiGaji::create([
        'karyawan_id' => $karyawanB->id, 'bulan' => 7, 'tahun' => 2026,
        'gaji_pokok' => 3000000, 'gaji_bersih' => 3000000,
    ]);

    $laporanRepository = app(LaporanRepository::class);

    $rekapB = $laporanRepository->rekapUnitKerjaPenggajian($laporanRepository->penggajianQuery([], 7, 2026));
    expect($rekapB)->toHaveCount(1)
        ->and($rekapB->first()->nama_unit)->toBe('Unit B');

    $this->actingAs($userA);
    $rekapA = $laporanRepository->rekapUnitKerjaPenggajian($laporanRepository->penggajianQuery([], 7, 2026));
    expect($rekapA)->toHaveCount(1)
        ->and($rekapA->first()->nama_unit)->toBe('Unit A');
});

test('mixing own barang id with another koperasi foto id is rejected by the ownership guard', function () {
    $koperasiA = Koperasi::create(['nama' => 'Koperasi A']);
    $koperasiB = Koperasi::create(['nama' => 'Koperasi B']);
    $userA = adminPrimerUser($koperasiA);
    $userB = adminPrimerUser($koperasiB);

    $this->actingAs($userA);
    $unitA = UnitKerja::create(['nama_unit' => 'Unit A']);
    $barangA = Barang::create([
        'kode_barang' => 'A-0001',
        'nama_barang' => 'Laptop A',
        'kategori' => 'Elektronik',
        'unit_kerja_id' => $unitA->id,
        'tanggal_perolehan' => '2026-01-01',
        'harga_perolehan' => 5000000,
    ]);
    $fotoA = $barangA->fotoPendukung()->create(['path' => 'fake/path-a.jpg']);

    $this->actingAs($userB);
    $unitB = UnitKerja::create(['nama_unit' => 'Unit B']);
    $barangB = Barang::create([
        'kode_barang' => 'B-0001',
        'nama_barang' => 'Laptop B',
        'kategori' => 'Elektronik',
        'unit_kerja_id' => $unitB->id,
        'tanggal_perolehan' => '2026-01-01',
        'harga_perolehan' => 5000000,
    ]);

    // $barangB memang milik koperasi B (lolos route-model-binding), tapi
    // $fotoA milik barang koperasi A — guard kepemilikan eksplisit di
    // controller (barang_id === $barang->id) yang harus menolak, terlepas
    // dari status tenant foto itu sendiri (FotoBarang tidak py koperasi_id
    // sendiri).
    $this->delete(route('barang.foto.destroy', [$barangB, $fotoA]))->assertNotFound();
});
