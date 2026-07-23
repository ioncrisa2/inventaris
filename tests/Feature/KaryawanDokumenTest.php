<?php

use App\Models\DokumenKaryawan;
use App\Models\Karyawan;
use App\Models\UnitKerja;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');

    $this->unitKerja = UnitKerja::create(['nama_unit' => 'IT', 'kode' => 'IT']);
    $this->karyawan = Karyawan::create([
        'nik' => 'EMP-001',
        'nama_lengkap' => 'Budi Santoso',
        'tanggal_lahir' => '1990-01-01',
        'unit_kerja_id' => $this->unitKerja->id,
        'jabatan' => 'Staf IT',
        'status_karyawan' => 'Tetap',
        'gaji_pokok' => 7000000,
    ]);
});

test('document can be uploaded by an authorized user', function () {
    $this->actingAs(adminUser());

    $file = UploadedFile::fake()->create('ijazah.pdf', 200, 'application/pdf');

    $this->post(route('karyawan.dokumen.store', $this->karyawan), [
        'jenis_dokumen' => 'Ijazah',
        'dokumen' => $file,
    ])->assertRedirect(route('karyawan.show', $this->karyawan));

    $dokumen = DokumenKaryawan::where('karyawan_id', $this->karyawan->id)->firstOrFail();
    expect($dokumen->jenis_dokumen)->toBe('Ijazah');
    expect($dokumen->nama_asli)->toBe('ijazah.pdf');
    Storage::disk('local')->assertExists($dokumen->path);

    // Disk privat tidak boleh punya symlink publik.
    expect(file_exists(public_path('storage/'.$dokumen->path)))->toBeFalse();
});

test('document can be downloaded by a user with karyawan.view permission', function () {
    $this->actingAs(adminUser());

    $dokumen = buatDokumenUntuk($this->karyawan);

    $this->get(route('karyawan.dokumen.download', [$this->karyawan, $dokumen]))
        ->assertOk();
});

test('document download is forbidden for a user without karyawan.view permission', function () {
    $tanpaAkses = adminUser();
    $tanpaAkses->syncRoles([]);
    $this->actingAs($tanpaAkses);

    $dokumen = buatDokumenUntuk($this->karyawan);

    $this->get(route('karyawan.dokumen.download', [$this->karyawan, $dokumen]))
        ->assertForbidden();
});

test('document can be deleted', function () {
    $this->actingAs(adminUser());

    $dokumen = buatDokumenUntuk($this->karyawan);

    $this->delete(route('karyawan.dokumen.destroy', [$this->karyawan, $dokumen]))
        ->assertRedirect(route('karyawan.show', $this->karyawan));

    $this->assertDatabaseMissing('dokumen_karyawan', ['id' => $dokumen->id]);
    Storage::disk('local')->assertMissing($dokumen->path);
});

test('deleting karyawan cascades its documents', function () {
    $this->actingAs(adminUser());

    $dokumen = buatDokumenUntuk($this->karyawan);

    $this->delete(route('karyawan.destroy', $this->karyawan))
        ->assertRedirect(route('karyawan.index'));

    $this->assertDatabaseMissing('dokumen_karyawan', ['id' => $dokumen->id]);
    Storage::disk('local')->assertMissing($dokumen->path);
});

test('jenis_dokumen outside the configured list is rejected', function () {
    $this->actingAs(adminUser());

    $file = UploadedFile::fake()->create('dokumen.pdf', 100, 'application/pdf');

    $this->post(route('karyawan.dokumen.store', $this->karyawan), [
        'jenis_dokumen' => 'Tidak Dikenal',
        'dokumen' => $file,
    ])->assertSessionHasErrors('jenis_dokumen');
});

test('oversized or invalid mime documents are rejected', function () {
    $this->actingAs(adminUser());

    $terlaluBesar = UploadedFile::fake()->create('besar.pdf', 6000, 'application/pdf');

    $this->post(route('karyawan.dokumen.store', $this->karyawan), [
        'jenis_dokumen' => 'Ijazah',
        'dokumen' => $terlaluBesar,
    ])->assertSessionHasErrors('dokumen');

    $mimeSalah = UploadedFile::fake()->create('dokumen.exe', 100, 'application/octet-stream');

    $this->post(route('karyawan.dokumen.store', $this->karyawan), [
        'jenis_dokumen' => 'Ijazah',
        'dokumen' => $mimeSalah,
    ])->assertSessionHasErrors('dokumen');
});

function buatDokumenUntuk(Karyawan $karyawan): DokumenKaryawan
{
    $path = UploadedFile::fake()->create('ijazah.pdf', 100, 'application/pdf')
        ->storeAs('dokumen-karyawan', 'ijazah-test.pdf', 'local');

    return DokumenKaryawan::create([
        'karyawan_id' => $karyawan->id,
        'jenis_dokumen' => 'Ijazah',
        'nama_asli' => 'ijazah.pdf',
        'path' => $path,
    ]);
}
