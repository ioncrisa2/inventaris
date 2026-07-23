<?php

use App\Models\Barang;
use App\Models\DokumenBarang;
use App\Models\UnitKerja;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');

    $this->unitKerja = UnitKerja::create(['nama_unit' => 'IT', 'kode' => 'IT']);
    $this->barang = Barang::create([
        'kode_barang' => 'IT-KL1-2026-0001',
        'nama_barang' => 'Laptop Operasional',
        'kategori' => 'Bukan Bangunan - Kelompok 1',
        'unit_kerja_id' => $this->unitKerja->id,
        'tanggal_perolehan' => now()->subMonth(),
        'harga_perolehan' => 12000000,
    ]);
});

test('document can be uploaded by an authorized user', function () {
    $this->actingAs(adminUser());

    $file = UploadedFile::fake()->create('nota.pdf', 200, 'application/pdf');

    $this->post(route('barang.dokumen.store', $this->barang), [
        'jenis_dokumen' => 'Nota Pembelian',
        'dokumen' => $file,
    ])->assertRedirect(route('barang.show', $this->barang));

    $dokumen = DokumenBarang::where('barang_id', $this->barang->id)->firstOrFail();
    expect($dokumen->jenis_dokumen)->toBe('Nota Pembelian');
    expect($dokumen->nama_asli)->toBe('nota.pdf');
    Storage::disk('local')->assertExists($dokumen->path);

    // Disk privat tidak boleh punya symlink publik.
    expect(file_exists(public_path('storage/'.$dokumen->path)))->toBeFalse();
});

test('document can be downloaded by a user with barang.view permission', function () {
    $this->actingAs(adminUser());

    $dokumen = buatDokumenBarangUntuk($this->barang);

    $this->get(route('barang.dokumen.download', [$this->barang, $dokumen]))
        ->assertOk();
});

test('document download is forbidden for a user without barang.view permission', function () {
    $tanpaAkses = adminUser();
    $tanpaAkses->syncRoles([]);
    $this->actingAs($tanpaAkses);

    $dokumen = buatDokumenBarangUntuk($this->barang);

    $this->get(route('barang.dokumen.download', [$this->barang, $dokumen]))
        ->assertForbidden();
});

test('document can be deleted', function () {
    $this->actingAs(adminUser());

    $dokumen = buatDokumenBarangUntuk($this->barang);

    $this->delete(route('barang.dokumen.destroy', [$this->barang, $dokumen]))
        ->assertRedirect(route('barang.show', $this->barang));

    $this->assertDatabaseMissing('dokumen_barang', ['id' => $dokumen->id]);
    Storage::disk('local')->assertMissing($dokumen->path);
});

test('barang with documents cannot be deleted and its files remain', function () {
    $this->actingAs(adminUser());

    $dokumen = buatDokumenBarangUntuk($this->barang);

    $this->from(route('barang.show', $this->barang))
        ->delete(route('barang.destroy', $this->barang))
        ->assertRedirect(route('barang.show', $this->barang))
        ->assertSessionHas('error');

    $this->assertDatabaseHas('barang', ['id' => $this->barang->id]);
    $this->assertDatabaseHas('dokumen_barang', ['id' => $dokumen->id]);
    Storage::disk('local')->assertExists($dokumen->path);
});

test('jenis_dokumen outside the configured list is rejected', function () {
    $this->actingAs(adminUser());

    $file = UploadedFile::fake()->create('dokumen.pdf', 100, 'application/pdf');

    $this->post(route('barang.dokumen.store', $this->barang), [
        'jenis_dokumen' => 'Tidak Dikenal',
        'dokumen' => $file,
    ])->assertSessionHasErrors('jenis_dokumen');
});

test('oversized or invalid mime documents are rejected', function () {
    $this->actingAs(adminUser());

    $terlaluBesar = UploadedFile::fake()->create('besar.pdf', 6000, 'application/pdf');

    $this->post(route('barang.dokumen.store', $this->barang), [
        'jenis_dokumen' => 'Nota Pembelian',
        'dokumen' => $terlaluBesar,
    ])->assertSessionHasErrors('dokumen');

    $mimeSalah = UploadedFile::fake()->create('dokumen.exe', 100, 'application/octet-stream');

    $this->post(route('barang.dokumen.store', $this->barang), [
        'jenis_dokumen' => 'Nota Pembelian',
        'dokumen' => $mimeSalah,
    ])->assertSessionHasErrors('dokumen');
});

function buatDokumenBarangUntuk(Barang $barang): DokumenBarang
{
    $path = UploadedFile::fake()->create('nota.pdf', 100, 'application/pdf')
        ->storeAs('dokumen-barang', 'nota-test.pdf', 'local');

    return DokumenBarang::create([
        'barang_id' => $barang->id,
        'jenis_dokumen' => 'Nota Pembelian',
        'nama_asli' => 'nota.pdf',
        'path' => $path,
    ]);
}
