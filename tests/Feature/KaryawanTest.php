<?php

use App\Models\DokumenKaryawan;
use App\Models\Karyawan;
use App\Models\UnitKerja;
use App\Services\KaryawanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(adminUser());
    $this->unitKerja = UnitKerja::create(['nama_unit' => 'IT']);
    Storage::fake('local');
    Storage::fake('public');
});

function payloadKaryawan(UnitKerja $unitKerja, array $override = []): array
{
    $payload = array_merge([
        'nik' => 'EMP-001',
        'nama_lengkap' => 'Budi Santoso',
        'tempat_lahir' => 'Palembang',
        'tanggal_lahir' => '1990-01-01',
        'jenis_kelamin' => 'Laki-laki',
        'agama' => 'Islam',
        'status_perkawinan' => 'Kawin',
        'nomor_ktp' => '1671010101900001',
        'npwp' => '09.123.456.7-301.000',
        'pendidikan_terakhir' => 'S1',
        'jurusan' => 'Teknik Informatika',
        'nama_sekolah' => 'Universitas Sriwijaya',
        'tahun_lulus' => 2012,
        'foto_karyawan' => UploadedFile::fake()->image('foto.jpg'),
        'unit_kerja_id' => $unitKerja->id,
        'tanggal_masuk_kerja' => '2020-01-01',
        'jabatan' => 'Staf IT',
        'status_karyawan' => 'Tetap',
        'nomor_sk_pengangkatan' => 'SK/001/2020',
        'tanggal_sk_pengangkatan' => '2020-01-01',
        'gaji_pokok' => 7000000,
    ], $override);

    // Nilai override eksplisit null berarti field tsb sengaja tidak dikirim
    // sama sekali (dipakai untuk menguji foto_karyawan opsional saat update).
    return array_filter($payload, fn ($nilai) => $nilai !== null);
}

test('karyawan can be created', function () {
    $this->get(route('karyawan.create'))
        ->assertOk()
        ->assertSee('Simpan Karyawan')
        ->assertSee('IT');

    $this->post(route('karyawan.store'), payloadKaryawan($this->unitKerja))
        ->assertRedirect(route('karyawan.index'));

    $karyawan = Karyawan::where('nik', 'EMP-001')->firstOrFail();

    $this->assertDatabaseHas('karyawan', [
        'nik' => 'EMP-001',
        'nama_lengkap' => 'Budi Santoso',
        'unit_kerja_id' => $this->unitKerja->id,
        'status_karyawan' => 'Tetap',
        'nomor_ktp' => '1671010101900001',
    ]);

    expect($karyawan->foto_karyawan)->not->toBeNull();
    Storage::disk('public')->assertExists($karyawan->foto_karyawan);
});

test('karyawan can be viewed and updated', function () {
    $this->post(route('karyawan.store'), payloadKaryawan($this->unitKerja))
        ->assertRedirect(route('karyawan.index'));

    $karyawan = Karyawan::where('nik', 'EMP-001')->firstOrFail();

    $this->get(route('karyawan.show', $karyawan))
        ->assertOk()
        ->assertSee('Budi Santoso')
        ->assertSee('Rp 7.000.000')
        ->assertSee('Lihat Absensi')
        ->assertSee(route('absensi.show', $karyawan), false);

    $this->get(route('karyawan.edit', $karyawan))
        ->assertOk()
        ->assertSee('Simpan Perubahan');

    // foto_karyawan tidak dikirim ulang -- foto lama harus tetap dipakai.
    $this->put(route('karyawan.update', $karyawan), payloadKaryawan($this->unitKerja, [
        'nama_lengkap' => 'Budi Santoso Updated',
        'jabatan' => 'Senior Staf IT',
        'status_karyawan' => 'Honorer',
        'gaji_pokok' => 8000000,
        'foto_karyawan' => null,
    ]))->assertRedirect(route('karyawan.index'));

    $karyawan->refresh();

    $this->assertDatabaseHas('karyawan', [
        'id' => $karyawan->id,
        'nama_lengkap' => 'Budi Santoso Updated',
        'status_karyawan' => 'Honorer',
    ]);
    expect($karyawan->foto_karyawan)->not->toBeNull();
});

test('karyawan tidak wajib upload ulang foto saat foto lama sudah ada, tapi wajib kalau belum ada foto sama sekali', function () {
    $karyawan = Karyawan::create(array_merge(payloadKaryawanDasar($this->unitKerja), [
        'foto_karyawan' => null,
    ]));

    // Belum ada foto sama sekali -> wajib diisi.
    $this->put(route('karyawan.update', $karyawan), payloadKaryawan($this->unitKerja, ['foto_karyawan' => null]))
        ->assertSessionHasErrors('foto_karyawan');

    // Setelah foto ada, update lain tanpa foto baru tidak boleh gagal.
    $karyawan->update(['foto_karyawan' => 'karyawan-foto/sudah-ada.jpg']);

    $this->put(route('karyawan.update', $karyawan), payloadKaryawan($this->unitKerja, ['foto_karyawan' => null]))
        ->assertSessionDoesntHaveErrors('foto_karyawan');
});

test('nomor_ktp harus 16 digit', function () {
    $this->post(route('karyawan.store'), payloadKaryawan($this->unitKerja, ['nomor_ktp' => '12345']))
        ->assertSessionHasErrors('nomor_ktp');
});

test('gaji pokok di luar DECIMAL 15 2 dan notasi ilmiah ditolak', function () {
    foreach (['10000000000000.00', '1e3'] as $nilai) {
        $this->post(route('karyawan.store'), payloadKaryawan($this->unitKerja, [
            'gaji_pokok' => $nilai,
        ]))->assertSessionHasErrors('gaji_pokok');
    }

    expect(Karyawan::count())->toBe(0);
});

test('atasan_langsung_id tidak boleh diri sendiri', function () {
    $karyawan = Karyawan::create(payloadKaryawanDasar($this->unitKerja));

    $this->put(route('karyawan.update', $karyawan), payloadKaryawan($this->unitKerja, [
        'atasan_langsung_id' => $karyawan->id,
        'foto_karyawan' => null,
    ]))->assertSessionHasErrors('atasan_langsung_id');
});

test('masa kerja dihitung dari tanggal masuk kerja', function () {
    $karyawan = Karyawan::create(array_merge(payloadKaryawanDasar($this->unitKerja), [
        'tanggal_masuk_kerja' => now()->subYears(3)->subMonths(2)->toDateString(),
    ]));

    $masaKerja = app(KaryawanService::class)->masaKerja($karyawan);

    expect($masaKerja)->toBe('3 tahun 2 bulan');
});

test('dokumen repeater rows are uploaded when creating karyawan', function () {
    $ijazah = UploadedFile::fake()->create('ijazah.pdf', 200, 'application/pdf');
    $ktp = UploadedFile::fake()->create('ktp.jpg', 100, 'image/jpeg');

    $this->post(route('karyawan.store'), payloadKaryawan($this->unitKerja, [
        'dokumen' => [
            ['jenis_dokumen' => 'Ijazah', 'dokumen' => $ijazah],
            ['jenis_dokumen' => 'KTP', 'dokumen' => $ktp],
        ],
    ]))->assertRedirect(route('karyawan.index'));

    $karyawan = Karyawan::where('nik', 'EMP-001')->firstOrFail();
    expect($karyawan->dokumen)->toHaveCount(2);

    $dokumenIjazah = $karyawan->dokumen->firstWhere('jenis_dokumen', 'Ijazah');
    expect($dokumenIjazah->nama_asli)->toBe('ijazah.pdf');
    Storage::disk('local')->assertExists($dokumenIjazah->path);

    $dokumenKtp = $karyawan->dokumen->firstWhere('jenis_dokumen', 'KTP');
    Storage::disk('local')->assertExists($dokumenKtp->path);
});

test('dokumen repeater rows are uploaded when updating karyawan, in addition to documents already on file', function () {
    $karyawan = Karyawan::create(array_merge(payloadKaryawanDasar($this->unitKerja), [
        'foto_karyawan' => 'karyawan-foto/sudah-ada.jpg',
    ]));

    DokumenKaryawan::create([
        'karyawan_id' => $karyawan->id,
        'jenis_dokumen' => 'Ijazah',
        'nama_asli' => 'ijazah-lama.pdf',
        'path' => 'dokumen-karyawan/ijazah-lama.pdf',
    ]);

    $sertifikat = UploadedFile::fake()->create('sertifikat.pdf', 150, 'application/pdf');

    $this->put(route('karyawan.update', $karyawan), payloadKaryawan($this->unitKerja, [
        'foto_karyawan' => null,
        'dokumen' => [
            ['jenis_dokumen' => 'Sertifikat Pelatihan', 'dokumen' => $sertifikat],
        ],
    ]))->assertRedirect(route('karyawan.index'));

    $karyawan->refresh();
    expect($karyawan->dokumen)->toHaveCount(2);

    $dokumenBaru = $karyawan->dokumen->firstWhere('jenis_dokumen', 'Sertifikat Pelatihan');
    expect($dokumenBaru->nama_asli)->toBe('sertifikat.pdf');
    Storage::disk('local')->assertExists($dokumenBaru->path);
});

test('empty dokumen repeater rows left over from add/remove are silently ignored', function () {
    $this->post(route('karyawan.store'), payloadKaryawan($this->unitKerja, [
        'dokumen' => [
            ['jenis_dokumen' => '', 'dokumen' => ''],
        ],
    ]))->assertRedirect(route('karyawan.index'));

    $karyawan = Karyawan::where('nik', 'EMP-001')->firstOrFail();
    expect($karyawan->dokumen)->toHaveCount(0);
});

test('dokumen repeater row with file but no jenis_dokumen is rejected', function () {
    $file = UploadedFile::fake()->create('dokumen.pdf', 100, 'application/pdf');

    $this->post(route('karyawan.store'), payloadKaryawan($this->unitKerja, [
        'dokumen' => [
            ['jenis_dokumen' => '', 'dokumen' => $file],
        ],
    ]))->assertSessionHasErrors('dokumen.0.jenis_dokumen');

    $this->assertDatabaseMissing('karyawan', ['nik' => 'EMP-001']);
});

test('karyawan index uses records and filters from the controller', function () {
    Karyawan::create(payloadKaryawanDasar($this->unitKerja, [
        'nik' => 'EMP-001',
        'nama_lengkap' => 'Budi Santoso',
        'jabatan' => 'Staf IT',
        'status_karyawan' => 'Tetap',
        'gaji_pokok' => 7000000,
    ]));

    Karyawan::create(payloadKaryawanDasar($this->unitKerja, [
        'nik' => 'EMP-002',
        'nama_lengkap' => 'Sari Utami',
        'jabatan' => 'Analis',
        'status_karyawan' => 'Honorer',
        'gaji_pokok' => 6000000,
    ]));

    $this->get(route('karyawan.index', ['status_karyawan' => 'Tetap']))
        ->assertOk()
        ->assertSee('Budi Santoso')
        ->assertDontSee('Sari Utami');

    $this->get(route('karyawan.index', ['search' => 'Sari']))
        ->assertOk()
        ->assertSee('Sari Utami')
        ->assertDontSee('Budi Santoso');
});

test('karyawan index supports incomplete core data drill down from dashboard', function () {
    $lengkap = Karyawan::create(payloadKaryawanDasar($this->unitKerja, [
        'nik' => 'EMP-010',
        'nama_lengkap' => 'Data Lengkap',
        'nomor_ktp' => '1671010101900010',
        'foto_karyawan' => 'karyawan-foto/lengkap.jpg',
    ]));
    Karyawan::create(payloadKaryawanDasar($this->unitKerja, [
        'nik' => 'EMP-011',
        'nama_lengkap' => 'Data Belum Lengkap',
        'nomor_ktp' => null,
        'foto_karyawan' => null,
    ]));
    DokumenKaryawan::create([
        'karyawan_id' => $lengkap->id,
        'jenis_dokumen' => 'KTP',
        'nama_asli' => 'ktp.jpg',
        'path' => 'dokumen-karyawan/ktp.jpg',
    ]);

    $this->get(route('karyawan.index', ['kelengkapan' => 'data-inti']))
        ->assertOk()
        ->assertSee('Data Belum Lengkap')
        ->assertDontSee('Data Lengkap');
});

test('karyawan can be deleted', function () {
    $karyawan = Karyawan::create(payloadKaryawanDasar($this->unitKerja));

    $this->delete(route('karyawan.destroy', $karyawan))
        ->assertRedirect(route('karyawan.index'));

    $this->assertDatabaseMissing('karyawan', ['id' => $karyawan->id]);
});

test('employment status is managed separately from the regular edit form', function () {
    $karyawan = Karyawan::create(payloadKaryawanDasar($this->unitKerja));

    $this->get(route('karyawan.edit', $karyawan))
        ->assertOk()
        ->assertDontSee('name="tanggal_mengundurkan_diri"', false);

    $this->get(route('karyawan.show', $karyawan))
        ->assertOk()
        ->assertSee('Nonaktifkan Karyawan')
        ->assertSee(route('karyawan.status-keaktifan.update', $karyawan), false);

    $this->patch(route('karyawan.status-keaktifan.update', $karyawan), [
        '_modal' => 'employmentStatusModal',
        'tanggal_mengundurkan_diri' => '2026-07-01',
    ])->assertRedirect(route('karyawan.show', $karyawan));

    expect($karyawan->fresh()->tanggal_mengundurkan_diri?->toDateString())->toBe('2026-07-01');

    $this->patch(route('karyawan.status-keaktifan.update', $karyawan), [
        '_modal' => 'employmentStatusModal',
        'tanggal_mengundurkan_diri' => '',
    ])->assertRedirect(route('karyawan.show', $karyawan));

    expect($karyawan->fresh()->tanggal_mengundurkan_diri)->toBeNull();
});

test('employment status rejects a departure date before employment began', function () {
    $karyawan = Karyawan::create(payloadKaryawanDasar($this->unitKerja));

    $this->from(route('karyawan.show', $karyawan))
        ->patch(route('karyawan.status-keaktifan.update', $karyawan), [
            '_modal' => 'employmentStatusModal',
            'tanggal_mengundurkan_diri' => '2019-12-31',
        ])
        ->assertRedirect(route('karyawan.show', $karyawan))
        ->assertSessionHasErrors('tanggal_mengundurkan_diri');

    expect($karyawan->fresh()->tanggal_mengundurkan_diri)->toBeNull();
});

/**
 * Payload minimal untuk Karyawan::create() langsung (bukan lewat HTTP, jadi
 * tidak divalidasi FormRequest) -- dipakai sebagai data awal di test yang
 * fokusnya bukan soal validasi field baru.
 */
function payloadKaryawanDasar(UnitKerja $unitKerja, array $override = []): array
{
    return array_merge([
        'nik' => 'EMP-001',
        'nama_lengkap' => 'Budi Santoso',
        'tanggal_lahir' => '1990-01-01',
        'unit_kerja_id' => $unitKerja->id,
        'tanggal_masuk_kerja' => '2020-01-01',
        'jabatan' => 'Staf IT',
        'status_karyawan' => 'Tetap',
        'gaji_pokok' => 7000000,
    ], $override);
}
