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
    $this->admin = adminUser();
    $this->actingAs($this->admin);

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

test('document can be downloaded by a user with karyawan.view permission', function () {
    $this->actingAs($this->admin);

    $dokumen = buatDokumenUntuk($this->karyawan);

    $this->get(route('karyawan.dokumen.download', [$this->karyawan, $dokumen]))
        ->assertOk();
});

test('document download is forbidden for a user without karyawan.view permission', function () {
    $tanpaAkses = staffUser(['koperasi_id' => $this->admin->koperasi_id]);
    $tanpaAkses->syncRoles([]);
    $this->actingAs($tanpaAkses);

    $dokumen = buatDokumenUntuk($this->karyawan);

    $this->get(route('karyawan.dokumen.download', [$this->karyawan, $dokumen]))
        ->assertForbidden();
});

test('deleting karyawan cascades its documents', function () {
    $this->actingAs($this->admin);

    $dokumen = buatDokumenUntuk($this->karyawan);

    $this->delete(route('karyawan.destroy', $this->karyawan))
        ->assertRedirect(route('karyawan.index'));

    $this->assertDatabaseMissing('dokumen_karyawan', ['id' => $dokumen->id]);
    Storage::disk('local')->assertMissing($dokumen->path);
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
