<?php

use App\Models\Barang;
use App\Models\DokumenBarang;
use App\Models\RiwayatKondisiBarang;
use App\Models\UnitKerja;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(adminUser());
    $this->unitKerja = UnitKerja::create(['nama_unit' => 'IT']);
});

test('barang is created with a default initial condition', function () {
    $this->get(route('barang.create'))
        ->assertOk()
        ->assertSee('Simpan Barang')
        ->assertSee('IT');

    $this->post(route('barang.store'), [
        'nama_barang' => 'Laptop Operasional',
        'kategori' => 'Bukan Bangunan - Kelompok 1',
        'unit_kerja_id' => $this->unitKerja->id,
        'tanggal_perolehan' => now()->subDay()->toDateString(),
        'harga_perolehan' => 12000000,
    ])->assertRedirect(route('barang.index'));

    $barang = Barang::where('nama_barang', 'Laptop Operasional')->firstOrFail();

    // Kode barang dibuat otomatis oleh sistem (bukan diketik pengguna),
    // mengikuti template default {UNIT}-{KATEGORI}-{TAHUN}-{URUT}.
    expect($barang->kode_barang)->toBe('IT-KL1-'.now()->format('Y').'-0001');

    $this->assertDatabaseHas('barang', [
        'id' => $barang->id,
        'nama_barang' => 'Laptop Operasional',
        'unit_kerja_id' => $this->unitKerja->id,
    ]);
    $this->assertDatabaseHas('riwayat_kondisi_barang', [
        'barang_id' => $barang->id,
        'kondisi' => 'Baru',
        'keterangan' => 'Kondisi awal saat barang ditambahkan (otomatis: Baru).',
    ]);
});

test('barang accepts any kategori and kondisi option offered by the config list', function () {
    $this->post(route('barang.store'), [
        'nama_barang' => 'Kursi Roda',
        'kategori' => 'Bangunan - Permanen',
        'unit_kerja_id' => $this->unitKerja->id,
        'tanggal_perolehan' => now()->subDay()->toDateString(),
        'harga_perolehan' => 1500000,
    ])->assertRedirect(route('barang.index'));

    $barang = Barang::where('nama_barang', 'Kursi Roda')->firstOrFail();

    $this->assertDatabaseHas('barang', [
        'id' => $barang->id,
        'kategori' => 'Bangunan - Permanen',
    ]);

    $this->post(route('barang.kondisi.store', $barang), [
        'tanggal_pemeriksaan' => now()->toDateString(),
        'kondisi' => 'Perlu Perawatan',
    ])->assertRedirect(route('barang.show', $barang));

    $this->assertDatabaseHas('riwayat_kondisi_barang', [
        'barang_id' => $barang->id,
        'kondisi' => 'Perlu Perawatan',
    ]);
});

test('master data update does not change condition history', function () {
    $barang = Barang::create([
        'kode_barang' => 'INV-001',
        'nama_barang' => 'Laptop Operasional',
        'kategori' => 'Bukan Bangunan - Kelompok 1',
        'unit_kerja_id' => $this->unitKerja->id,
        'tanggal_perolehan' => now()->subMonth(),
        'harga_perolehan' => 12000000,
    ]);

    RiwayatKondisiBarang::create([
        'barang_id' => $barang->id,
        'tanggal_pemeriksaan' => now()->subDay(),
        'kondisi' => 'Baik',
        'keterangan' => 'Kondisi awal',
    ]);

    $this->get(route('barang.show', $barang))
        ->assertOk()
        ->assertSee('Laptop Operasional')
        ->assertSee('Rp 12.000.000')
        ->assertSee('Kondisi awal');

    $this->get(route('barang.edit', $barang))
        ->assertOk()
        ->assertSee('Simpan Perubahan');

    $this->put(route('barang.update', $barang), [
        'kode_barang' => 'INV-001',
        'nama_barang' => 'Laptop Operasional Updated',
        'kategori' => 'Bukan Bangunan - Kelompok 1',
        'unit_kerja_id' => $this->unitKerja->id,
        'tanggal_perolehan' => now()->subMonth()->toDateString(),
        'harga_perolehan' => 12500000,
    ])->assertRedirect(route('barang.index'));

    $this->assertDatabaseHas('barang', [
        'id' => $barang->id,
        'nama_barang' => 'Laptop Operasional Updated',
    ]);
    expect(RiwayatKondisiBarang::where('barang_id', $barang->id)->count())->toBe(1);
});

test('condition inspections are appended even when the status is unchanged', function () {
    $barang = Barang::create([
        'kode_barang' => 'INV-001',
        'nama_barang' => 'Laptop Operasional',
        'kategori' => 'Bukan Bangunan - Kelompok 1',
        'unit_kerja_id' => $this->unitKerja->id,
        'tanggal_perolehan' => now()->subMonth(),
        'harga_perolehan' => 12000000,
    ]);

    RiwayatKondisiBarang::create([
        'barang_id' => $barang->id,
        'tanggal_pemeriksaan' => now()->subDay(),
        'kondisi' => 'Baik',
    ]);

    $payload = [
        'tanggal_pemeriksaan' => now()->toDateString(),
        'kondisi' => 'Rusak Ringan',
        'keterangan' => 'Keyboard perlu diperbaiki',
        'biaya_perbaikan' => 450000,
    ];

    $this->post(route('barang.kondisi.store', $barang), $payload)
        ->assertRedirect(route('barang.show', $barang));

    $this->post(route('barang.kondisi.store', $barang), [
        ...$payload,
        'keterangan' => 'Pemeriksaan lanjutan',
    ])->assertRedirect(route('barang.show', $barang));

    $this->assertDatabaseHas('riwayat_kondisi_barang', [
        'barang_id' => $barang->id,
        'kondisi' => 'Rusak Ringan',
        'keterangan' => 'Keyboard perlu diperbaiki',
        'biaya_perbaikan' => 450000,
    ]);
    expect(RiwayatKondisiBarang::where('barang_id', $barang->id)->count())->toBe(3);
});

test('inspection date cannot precede acquisition or exceed today', function () {
    $barang = Barang::create([
        'kode_barang' => 'INV-001',
        'nama_barang' => 'Laptop Operasional',
        'kategori' => 'Bukan Bangunan - Kelompok 1',
        'unit_kerja_id' => $this->unitKerja->id,
        'tanggal_perolehan' => now()->subWeek(),
        'harga_perolehan' => 12000000,
    ]);

    $this->from(route('barang.show', $barang))
        ->post(route('barang.kondisi.store', $barang), [
            'tanggal_pemeriksaan' => now()->subMonth()->toDateString(),
            'kondisi' => 'Baik',
        ])
        ->assertRedirect(route('barang.show', $barang))
        ->assertSessionHasErrors('tanggal_pemeriksaan');

    $this->from(route('barang.show', $barang))
        ->post(route('barang.kondisi.store', $barang), [
            'tanggal_pemeriksaan' => now()->addDay()->toDateString(),
            'kondisi' => 'Baik',
        ])
        ->assertRedirect(route('barang.show', $barang))
        ->assertSessionHasErrors('tanggal_pemeriksaan');
});

test('barang index displays and filters controller records', function () {
    Barang::create([
        'kode_barang' => 'INV-001',
        'nama_barang' => 'Laptop Operasional',
        'kategori' => 'Bukan Bangunan - Kelompok 1',
        'unit_kerja_id' => $this->unitKerja->id,
        'tanggal_perolehan' => now()->subMonth(),
        'harga_perolehan' => 12000000,
    ]);

    Barang::create([
        'kode_barang' => 'INV-002',
        'nama_barang' => 'Kursi Kantor',
        'kategori' => 'Bukan Bangunan - Kelompok 2',
        'unit_kerja_id' => $this->unitKerja->id,
        'tanggal_perolehan' => now()->subMonth(),
        'harga_perolehan' => 1000000,
    ]);

    $this->get(route('barang.index', ['kategori' => 'Bukan Bangunan - Kelompok 1']))
        ->assertOk()
        ->assertSee('Laptop Operasional')
        ->assertDontSee('Kursi Kantor');

    $this->get(route('barang.index', ['search' => 'Kursi']))
        ->assertOk()
        ->assertSee('Kursi Kantor')
        ->assertDontSee('Laptop Operasional');
});

test('barang index supports dashboard condition and completeness drill downs', function () {
    $lengkap = Barang::create([
        'kode_barang' => 'INV-010',
        'nama_barang' => 'Laptop Lengkap',
        'kategori' => 'Bukan Bangunan - Kelompok 1',
        'unit_kerja_id' => $this->unitKerja->id,
        'tanggal_perolehan' => now()->subMonth(),
        'harga_perolehan' => 12000000,
        'foto_sampul' => 'barang-sampul/laptop.jpg',
    ]);
    $belumLengkap = Barang::create([
        'kode_barang' => 'INV-011',
        'nama_barang' => 'Printer Belum Lengkap',
        'kategori' => 'Bukan Bangunan - Kelompok 1',
        'unit_kerja_id' => $this->unitKerja->id,
        'tanggal_perolehan' => now()->subMonth(),
        'harga_perolehan' => 2000000,
    ]);

    RiwayatKondisiBarang::create([
        'barang_id' => $lengkap->id,
        'tanggal_pemeriksaan' => now(),
        'kondisi' => 'Baik',
    ]);
    RiwayatKondisiBarang::create([
        'barang_id' => $belumLengkap->id,
        'tanggal_pemeriksaan' => now(),
        'kondisi' => 'Rusak Berat',
    ]);
    DokumenBarang::create([
        'barang_id' => $lengkap->id,
        'jenis_dokumen' => 'Nota Pembelian',
        'nama_asli' => 'nota.pdf',
        'path' => 'dokumen-barang/nota.pdf',
    ]);

    $this->get(route('barang.index', ['kondisi' => 'bermasalah']))
        ->assertOk()
        ->assertSee('Printer Belum Lengkap')
        ->assertDontSee('Laptop Lengkap');

    $this->get(route('barang.index', ['kelengkapan' => 'tanpa-nota']))
        ->assertOk()
        ->assertSee('Printer Belum Lengkap')
        ->assertDontSee('Laptop Lengkap');
});

test('barang with condition history cannot be deleted', function () {
    $barang = Barang::create([
        'kode_barang' => 'INV-001',
        'nama_barang' => 'Laptop Operasional',
        'kategori' => 'Bukan Bangunan - Kelompok 1',
        'unit_kerja_id' => $this->unitKerja->id,
        'tanggal_perolehan' => now()->subMonth(),
        'harga_perolehan' => 12000000,
    ]);

    RiwayatKondisiBarang::create([
        'barang_id' => $barang->id,
        'tanggal_pemeriksaan' => now(),
        'kondisi' => 'Baik',
    ]);

    $this->from(route('barang.index'))
        ->delete(route('barang.destroy', $barang))
        ->assertRedirect(route('barang.index'))
        ->assertSessionHas('error');

    $this->assertDatabaseHas('barang', ['id' => $barang->id]);
    $this->assertDatabaseHas('riwayat_kondisi_barang', ['barang_id' => $barang->id]);

    expect(fn () => $barang->delete())->toThrow(QueryException::class);
});
