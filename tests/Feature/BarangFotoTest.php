<?php

use App\Models\Barang;
use App\Models\DokumenBarang;
use App\Models\FotoBarang;
use App\Models\UnitKerja;
use App\Services\BarangService;
use App\Services\FotoBarangService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(adminUser());
    $this->unitKerja = UnitKerja::create(['nama_unit' => 'IT', 'kode' => 'IT']);
    Storage::fake('public');
    Storage::fake('local');
});

test('foto sampul is stored when creating barang', function () {
    $file = UploadedFile::fake()->image('sampul.jpg');

    $this->post(route('barang.store'), [
        'nama_barang' => 'Laptop Operasional',
        'kategori' => 'Bukan Bangunan - Kelompok 1',
        'unit_kerja_id' => $this->unitKerja->id,
        'tanggal_perolehan' => now()->subDay()->toDateString(),
        'harga_perolehan' => 12000000,
        'foto_sampul' => $file,
    ])->assertRedirect(route('barang.index'));

    $barang = Barang::where('nama_barang', 'Laptop Operasional')->firstOrFail();

    expect($barang->foto_sampul)->not->toBeNull();
    Storage::disk('public')->assertExists($barang->foto_sampul);
});

test('foto sampul is replaced on update and the old file is deleted', function () {
    $barang = Barang::create([
        'kode_barang' => 'IT-ELK-2026-0001',
        'nama_barang' => 'Laptop Operasional',
        'kategori' => 'Bukan Bangunan - Kelompok 1',
        'unit_kerja_id' => $this->unitKerja->id,
        'tanggal_perolehan' => now()->subMonth(),
        'harga_perolehan' => 12000000,
        'foto_sampul' => 'barang-sampul/lama.jpg',
    ]);
    Storage::disk('public')->put('barang-sampul/lama.jpg', 'isi-lama');

    $fileBaru = UploadedFile::fake()->image('baru.jpg');

    $this->put(route('barang.update', $barang), [
        'nama_barang' => 'Laptop Operasional',
        'kategori' => 'Bukan Bangunan - Kelompok 1',
        'unit_kerja_id' => $this->unitKerja->id,
        'tanggal_perolehan' => now()->subMonth()->toDateString(),
        'harga_perolehan' => 12000000,
        'foto_sampul' => $fileBaru,
    ])->assertRedirect(route('barang.index'));

    $barang->refresh();

    Storage::disk('public')->assertMissing('barang-sampul/lama.jpg');
    Storage::disk('public')->assertExists($barang->foto_sampul);
    expect($barang->foto_sampul)->not->toBe('barang-sampul/lama.jpg');
});

test('foto baru dibersihkan dan foto lama dipertahankan ketika update database rollback', function () {
    $barang = Barang::create([
        'kode_barang' => 'IT-ELK-ROLLBACK-001',
        'nama_barang' => 'Laptop Rollback',
        'kategori' => 'Bukan Bangunan - Kelompok 1',
        'unit_kerja_id' => $this->unitKerja->id,
        'tanggal_perolehan' => now()->subMonth(),
        'harga_perolehan' => 12000000,
        'foto_sampul' => 'barang-sampul/lama.jpg',
    ]);
    Storage::disk('public')->put('barang-sampul/lama.jpg', 'isi-lama');

    expect(fn () => app(BarangService::class)->update($barang, [
        'unit_kerja_id' => 999999,
        'foto_sampul' => UploadedFile::fake()->image('baru.jpg'),
    ]))->toThrow(QueryException::class);

    expect($barang->refresh()->foto_sampul)->toBe('barang-sampul/lama.jpg');
    Storage::disk('public')->assertExists('barang-sampul/lama.jpg');
    expect(Storage::disk('public')->allFiles('barang-sampul'))->toBe(['barang-sampul/lama.jpg']);
});

test('foto sampul is preserved when update is submitted without a new file', function () {
    $barang = Barang::create([
        'kode_barang' => 'IT-ELK-2026-0001',
        'nama_barang' => 'Laptop Operasional',
        'kategori' => 'Bukan Bangunan - Kelompok 1',
        'unit_kerja_id' => $this->unitKerja->id,
        'tanggal_perolehan' => now()->subMonth(),
        'harga_perolehan' => 12000000,
        'foto_sampul' => 'barang-sampul/tetap.jpg',
    ]);
    Storage::disk('public')->put('barang-sampul/tetap.jpg', 'isi');

    $this->put(route('barang.update', $barang), [
        'nama_barang' => 'Laptop Operasional Updated',
        'kategori' => 'Bukan Bangunan - Kelompok 1',
        'unit_kerja_id' => $this->unitKerja->id,
        'tanggal_perolehan' => now()->subMonth()->toDateString(),
        'harga_perolehan' => 12000000,
    ])->assertRedirect(route('barang.index'));

    $barang->refresh();

    expect($barang->foto_sampul)->toBe('barang-sampul/tetap.jpg');
    Storage::disk('public')->assertExists('barang-sampul/tetap.jpg');
});

test('multiple foto pendukung can be uploaded together when creating barang', function () {
    $fotoSatu = UploadedFile::fake()->image('satu.jpg');
    $fotoDua = UploadedFile::fake()->image('dua.jpg');

    $this->post(route('barang.store'), [
        'nama_barang' => 'Printer Kantor',
        'kategori' => 'Bukan Bangunan - Kelompok 1',
        'unit_kerja_id' => $this->unitKerja->id,
        'tanggal_perolehan' => now()->subDay()->toDateString(),
        'harga_perolehan' => 3000000,
        'foto_pendukung' => [$fotoSatu, $fotoDua],
    ])->assertRedirect(route('barang.index'));

    $barang = Barang::where('nama_barang', 'Printer Kantor')->firstOrFail();

    expect($barang->fotoPendukung)->toHaveCount(2);
    foreach ($barang->fotoPendukung as $foto) {
        Storage::disk('public')->assertExists($foto->path);
    }
});

test('foto dari child service dibersihkan ketika transaksi induk rollback', function () {
    $barang = Barang::create([
        'kode_barang' => 'IT-ELK-ROLLBACK-CHILD',
        'nama_barang' => 'Laptop Rollback Child',
        'kategori' => 'Bukan Bangunan - Kelompok 1',
        'unit_kerja_id' => $this->unitKerja->id,
        'tanggal_perolehan' => now()->subMonth(),
        'harga_perolehan' => 12000000,
    ]);

    expect(fn () => DB::transaction(function () use ($barang) {
        app(FotoBarangService::class)->store($barang, [
            'foto' => UploadedFile::fake()->image('child.jpg'),
        ]);

        throw new RuntimeException('Paksa rollback transaksi induk.');
    }))->toThrow(RuntimeException::class);

    $this->assertDatabaseMissing('foto_barang', ['barang_id' => $barang->id]);
    expect(Storage::disk('public')->allFiles('barang-foto'))->toBe([]);
});

test('non-image files in foto pendukung array are rejected when creating barang', function () {
    $dokumen = UploadedFile::fake()->create('dokumen.pdf', 100, 'application/pdf');

    $this->post(route('barang.store'), [
        'nama_barang' => 'Printer Kantor',
        'kategori' => 'Bukan Bangunan - Kelompok 1',
        'unit_kerja_id' => $this->unitKerja->id,
        'tanggal_perolehan' => now()->subDay()->toDateString(),
        'harga_perolehan' => 3000000,
        'foto_pendukung' => [$dokumen],
    ])->assertSessionHasErrors('foto_pendukung.0');

    $this->assertDatabaseMissing('barang', ['nama_barang' => 'Printer Kantor']);
});

test('foto pendukung can be uploaded and deleted', function () {
    $barang = Barang::create([
        'kode_barang' => 'IT-ELK-2026-0001',
        'nama_barang' => 'Laptop Operasional',
        'kategori' => 'Bukan Bangunan - Kelompok 1',
        'unit_kerja_id' => $this->unitKerja->id,
        'tanggal_perolehan' => now()->subMonth(),
        'harga_perolehan' => 12000000,
    ]);

    $file = UploadedFile::fake()->image('pendukung.jpg');

    $this->post(route('barang.foto.store', $barang), [
        'foto' => $file,
        'keterangan' => 'Tampak samping',
    ])->assertRedirect(route('barang.show', $barang));

    $foto = FotoBarang::where('barang_id', $barang->id)->firstOrFail();
    expect($foto->keterangan)->toBe('Tampak samping');
    Storage::disk('public')->assertExists($foto->path);

    $this->delete(route('barang.foto.destroy', [$barang, $foto]))
        ->assertRedirect(route('barang.show', $barang));

    $this->assertDatabaseMissing('foto_barang', ['id' => $foto->id]);
    Storage::disk('public')->assertMissing($foto->path);
});

test('barang with supporting photos cannot be deleted and its files remain', function () {
    $barang = Barang::create([
        'kode_barang' => 'IT-ELK-2026-0001',
        'nama_barang' => 'Laptop Operasional',
        'kategori' => 'Bukan Bangunan - Kelompok 1',
        'unit_kerja_id' => $this->unitKerja->id,
        'tanggal_perolehan' => now()->subMonth(),
        'harga_perolehan' => 12000000,
        'foto_sampul' => 'barang-sampul/sampul.jpg',
    ]);
    Storage::disk('public')->put('barang-sampul/sampul.jpg', 'isi');

    $foto = FotoBarang::create([
        'barang_id' => $barang->id,
        'path' => 'barang-foto/pendukung.jpg',
        'keterangan' => null,
    ]);
    Storage::disk('public')->put('barang-foto/pendukung.jpg', 'isi');

    $this->from(route('barang.index'))
        ->delete(route('barang.destroy', $barang))
        ->assertRedirect(route('barang.index'))
        ->assertSessionHas('error');

    Storage::disk('public')->assertExists('barang-sampul/sampul.jpg');
    Storage::disk('public')->assertExists('barang-foto/pendukung.jpg');
    $this->assertDatabaseHas('barang', ['id' => $barang->id]);
    $this->assertDatabaseHas('foto_barang', ['id' => $foto->id]);
});

test('file barang tetap ada ketika transaksi terluar membatalkan penghapusan', function () {
    $barang = Barang::create([
        'kode_barang' => 'IT-ELK-ROLLBACK-DELETE',
        'nama_barang' => 'Laptop Rollback Delete',
        'kategori' => 'Bukan Bangunan - Kelompok 1',
        'unit_kerja_id' => $this->unitKerja->id,
        'tanggal_perolehan' => now()->subMonth(),
        'harga_perolehan' => 12000000,
        'foto_sampul' => 'barang-sampul/rollback-delete.jpg',
    ]);
    Storage::disk('public')->put($barang->foto_sampul, 'isi');
    $transactionLevel = DB::transactionLevel();

    DB::beginTransaction();

    try {
        app(BarangService::class)->destroy($barang);
        $this->assertDatabaseMissing('barang', ['id' => $barang->id]);
        Storage::disk('public')->assertExists($barang->foto_sampul);
    } finally {
        while (DB::transactionLevel() > $transactionLevel) {
            DB::rollBack();
        }
    }

    $this->assertDatabaseHas('barang', ['id' => $barang->id]);
    Storage::disk('public')->assertExists($barang->foto_sampul);
});

test('barcode and qr code use separate print pages', function () {
    $barang = Barang::create([
        'kode_barang' => 'IT-ELK-2026-0001',
        'nama_barang' => 'Laptop Operasional',
        'kategori' => 'Bukan Bangunan - Kelompok 1',
        'unit_kerja_id' => $this->unitKerja->id,
        'tanggal_perolehan' => now()->subMonth(),
        'harga_perolehan' => 12000000,
    ]);

    $barcodeResponse = $this->get(route('barang.barcode', $barang));

    $barcodeResponse->assertOk()
        ->assertViewIs('barang.barcode')
        ->assertSee($barang->kode_barang)
        ->assertSee($barang->nama_barang)
        ->assertSee(asset('assets/img/logo-koperasi.png'), false);

    $qrResponse = $this->get(route('barang.qr-code', $barang));

    $qrResponse->assertOk()
        ->assertViewIs('barang.qr-code')
        ->assertSee($barang->kode_barang)
        ->assertSee($barang->nama_barang)
        ->assertSee('Pindai untuk membuka detail barang.');

    expect(substr_count($barcodeResponse->getContent(), '<svg'))->toBe(1)
        ->and(substr_count($qrResponse->getContent(), '<svg'))->toBe(1);
});

test('selected barang can be exported as bulk barcode labels', function () {
    $selected = collect([
        ['IT-ELK-2026-0001', 'Laptop Operasional'],
        ['IT-ELK-2026-0002', 'Monitor Operasional'],
    ])->map(fn (array $data) => Barang::create([
        'kode_barang' => $data[0],
        'nama_barang' => $data[1],
        'kategori' => 'Bukan Bangunan - Kelompok 1',
        'unit_kerja_id' => $this->unitKerja->id,
        'tanggal_perolehan' => now()->subMonth(),
        'harga_perolehan' => 12000000,
    ]));

    $notSelected = Barang::create([
        'kode_barang' => 'IT-ELK-2026-0003',
        'nama_barang' => 'Printer Tidak Dipilih',
        'kategori' => 'Bukan Bangunan - Kelompok 1',
        'unit_kerja_id' => $this->unitKerja->id,
        'tanggal_perolehan' => now()->subMonth(),
        'harga_perolehan' => 5000000,
    ]);

    $response = $this->post(route('barang.barcode.bulk'), [
        'barang_ids' => $selected->pluck('id')->all(),
    ]);

    $response->assertOk()
        ->assertViewIs('barang.barcode-bulk')
        ->assertSee($selected[0]->nama_barang)
        ->assertSee($selected[1]->nama_barang)
        ->assertDontSee($notSelected->nama_barang)
        ->assertSee('Pengaturan Cetak')
        ->assertSee('id="barcodePaperSize"', false)
        ->assertSee('id="barcodeLabelSpacing"', false)
        ->assertSee('name="barcode_orientation"', false)
        ->assertSee('data-print-page', false)
        ->assertSee(asset('assets/img/logo-koperasi.png'), false);

    expect(substr_count($response->getContent(), '<svg'))->toBe(2);
});

test('bulk barcode export requires at least one barang', function () {
    $this->from(route('barang.index'))
        ->post(route('barang.barcode.bulk'), [])
        ->assertRedirect(route('barang.index'))
        ->assertSessionHasErrors('barang_ids');
});

test('barang index provides row selection for bulk barcode export', function () {
    Barang::create([
        'kode_barang' => 'IT-ELK-2026-0001',
        'nama_barang' => 'Laptop Operasional',
        'kategori' => 'Bukan Bangunan - Kelompok 1',
        'unit_kerja_id' => $this->unitKerja->id,
        'tanggal_perolehan' => now()->subMonth(),
        'harga_perolehan' => 12000000,
    ]);

    $this->get(route('barang.index'))
        ->assertOk()
        ->assertSee(route('barang.barcode.bulk'), false)
        ->assertSee(route('barang.bulk-destroy'), false)
        ->assertSee('data-bulk-select-all="barang"', false)
        ->assertSee('data-bulk-select="barang"', false)
        ->assertSee('Export Barcode');
});

test('dokumen repeater rows are uploaded when creating barang', function () {
    $nota = UploadedFile::fake()->create('nota.pdf', 200, 'application/pdf');
    $garansi = UploadedFile::fake()->create('garansi.jpg', 100, 'image/jpeg');

    $this->post(route('barang.store'), [
        'nama_barang' => 'Laptop Operasional',
        'kategori' => 'Bukan Bangunan - Kelompok 1',
        'unit_kerja_id' => $this->unitKerja->id,
        'tanggal_perolehan' => now()->subDay()->toDateString(),
        'harga_perolehan' => 12000000,
        'dokumen' => [
            ['jenis_dokumen' => 'Nota Pembelian', 'dokumen' => $nota],
            ['jenis_dokumen' => 'Kartu Garansi', 'dokumen' => $garansi],
        ],
    ])->assertRedirect(route('barang.index'));

    $barang = Barang::where('nama_barang', 'Laptop Operasional')->firstOrFail();
    expect($barang->dokumen)->toHaveCount(2);

    $dokumenNota = $barang->dokumen->firstWhere('jenis_dokumen', 'Nota Pembelian');
    expect($dokumenNota->nama_asli)->toBe('nota.pdf');
    Storage::disk('local')->assertExists($dokumenNota->path);

    $dokumenGaransi = $barang->dokumen->firstWhere('jenis_dokumen', 'Kartu Garansi');
    Storage::disk('local')->assertExists($dokumenGaransi->path);
});

test('dokumen repeater rows are uploaded when updating barang, in addition to documents already on file', function () {
    $barang = Barang::create([
        'kode_barang' => 'IT-KL1-2026-0001',
        'nama_barang' => 'Laptop Operasional',
        'kategori' => 'Bukan Bangunan - Kelompok 1',
        'unit_kerja_id' => $this->unitKerja->id,
        'tanggal_perolehan' => now()->subMonth(),
        'harga_perolehan' => 12000000,
    ]);

    DokumenBarang::create([
        'barang_id' => $barang->id,
        'jenis_dokumen' => 'Nota Pembelian',
        'nama_asli' => 'nota-lama.pdf',
        'path' => 'dokumen-barang/nota-lama.pdf',
    ]);

    $manual = UploadedFile::fake()->create('manual.pdf', 150, 'application/pdf');

    $this->put(route('barang.update', $barang), [
        'nama_barang' => 'Laptop Operasional',
        'kategori' => 'Bukan Bangunan - Kelompok 1',
        'unit_kerja_id' => $this->unitKerja->id,
        'tanggal_perolehan' => now()->subMonth()->toDateString(),
        'harga_perolehan' => 12000000,
        'dokumen' => [
            ['jenis_dokumen' => 'Manual/Buku Petunjuk', 'dokumen' => $manual],
        ],
    ])->assertRedirect(route('barang.index'));

    $barang->refresh();
    expect($barang->dokumen)->toHaveCount(2);

    $dokumenBaru = $barang->dokumen->firstWhere('jenis_dokumen', 'Manual/Buku Petunjuk');
    expect($dokumenBaru->nama_asli)->toBe('manual.pdf');
    Storage::disk('local')->assertExists($dokumenBaru->path);
});

test('empty dokumen repeater rows left over from add/remove are silently ignored', function () {
    $this->post(route('barang.store'), [
        'nama_barang' => 'Laptop Operasional',
        'kategori' => 'Bukan Bangunan - Kelompok 1',
        'unit_kerja_id' => $this->unitKerja->id,
        'tanggal_perolehan' => now()->subDay()->toDateString(),
        'harga_perolehan' => 12000000,
        'dokumen' => [
            ['jenis_dokumen' => '', 'dokumen' => ''],
        ],
    ])->assertRedirect(route('barang.index'));

    $barang = Barang::where('nama_barang', 'Laptop Operasional')->firstOrFail();
    expect($barang->dokumen)->toHaveCount(0);
});

test('dokumen repeater row with file but no jenis_dokumen is rejected', function () {
    $file = UploadedFile::fake()->create('dokumen.pdf', 100, 'application/pdf');

    $this->post(route('barang.store'), [
        'nama_barang' => 'Laptop Operasional',
        'kategori' => 'Bukan Bangunan - Kelompok 1',
        'unit_kerja_id' => $this->unitKerja->id,
        'tanggal_perolehan' => now()->subDay()->toDateString(),
        'harga_perolehan' => 12000000,
        'dokumen' => [
            ['jenis_dokumen' => '', 'dokumen' => $file],
        ],
    ])->assertSessionHasErrors('dokumen.0.jenis_dokumen');

    $this->assertDatabaseMissing('barang', ['nama_barang' => 'Laptop Operasional']);
});

test('non-image files are rejected for foto sampul and foto pendukung', function () {
    $barang = Barang::create([
        'kode_barang' => 'IT-ELK-2026-0001',
        'nama_barang' => 'Laptop Operasional',
        'kategori' => 'Bukan Bangunan - Kelompok 1',
        'unit_kerja_id' => $this->unitKerja->id,
        'tanggal_perolehan' => now()->subMonth(),
        'harga_perolehan' => 12000000,
    ]);

    $dokumen = UploadedFile::fake()->create('dokumen.pdf', 100, 'application/pdf');

    $this->post(route('barang.store'), [
        'nama_barang' => 'Barang Lain',
        'kategori' => 'Bukan Bangunan - Kelompok 1',
        'unit_kerja_id' => $this->unitKerja->id,
        'tanggal_perolehan' => now()->subDay()->toDateString(),
        'harga_perolehan' => 1000000,
        'foto_sampul' => $dokumen,
    ])->assertSessionHasErrors('foto_sampul');

    $this->post(route('barang.foto.store', $barang), [
        'foto' => $dokumen,
    ])->assertSessionHasErrors('foto');
});
