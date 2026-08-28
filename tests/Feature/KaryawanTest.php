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
        'alamat_ktp' => 'Jl. Merdeka No. 1, Palembang',
        'alamat_domisili' => 'Jl. Merdeka No. 1, Palembang',
        'pendidikan_terakhir' => 'S1',
        'jurusan' => 'Teknik Informatika',
        'nama_sekolah' => 'Universitas Sriwijaya',
        'tahun_lulus' => 2012,
        'foto_karyawan' => UploadedFile::fake()->image('foto.jpg'),
        'unit_kerja_id' => $unitKerja->id,
        'tanggal_masuk_kerja' => '2020-01-01',
        'jabatan' => 'Staf IT',
        'status_karyawan' => 'PKWTT',
        'nomor_sk_pengangkatan' => 'SK/001/2020',
        'tanggal_sk_pengangkatan' => '2020-01-01',
        'gaji_pokok' => 7000000,
    ], $override);

    // Nilai override eksplisit null berarti field tsb sengaja tidak dikirim sama sekali.
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
        'status_karyawan' => 'PKWTT',
        'nomor_ktp' => '1671010101900001',
    ]);

    expect($karyawan->foto_karyawan)->not->toBeNull();
    Storage::disk('public')->assertExists($karyawan->foto_karyawan);
});

test('npwp bersifat opsional', function () {
    $this->post(route('karyawan.store'), payloadKaryawan($this->unitKerja, [
        'npwp' => null,
    ]))->assertRedirect(route('karyawan.index'));

    $this->assertDatabaseHas('karyawan', [
        'nik' => 'EMP-001',
        'npwp' => null,
    ]);
});

test('nomor dan tanggal sk opsional untuk semua status karyawan', function () {
    foreach (Karyawan::STATUSES as $index => $status) {
        $this->post(route('karyawan.store'), payloadKaryawan($this->unitKerja, [
            'nik' => 'EMP-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
            'nomor_ktp' => '167101010190'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
            'status_karyawan' => $status,
            'nomor_sk_pengangkatan' => null,
            'tanggal_sk_pengangkatan' => null,
        ]))->assertRedirect(route('karyawan.index'));
    }

    expect(Karyawan::query()
        ->whereNull('nomor_sk_pengangkatan')
        ->whereNull('tanggal_sk_pengangkatan')
        ->count())->toBe(count(Karyawan::STATUSES));
});

test('nomor dan tanggal sk dapat disimpan untuk karyawan honorer', function () {
    $this->post(route('karyawan.store'), payloadKaryawan($this->unitKerja, [
        'status_karyawan' => 'Honorer',
        'nomor_sk_pengangkatan' => 'SK/HONORER/001',
        'tanggal_sk_pengangkatan' => '2020-01-01',
    ]))->assertRedirect(route('karyawan.index'));

    $this->assertDatabaseHas('karyawan', [
        'nik' => 'EMP-001',
        'status_karyawan' => 'Honorer',
        'nomor_sk_pengangkatan' => 'SK/HONORER/001',
        'tanggal_sk_pengangkatan' => '2020-01-01',
    ]);
});

test('karyawan can be viewed', function () {
    $this->post(route('karyawan.store'), payloadKaryawan($this->unitKerja))
        ->assertRedirect(route('karyawan.index'));

    $karyawan = Karyawan::where('nik', 'EMP-001')->firstOrFail();

    $this->get(route('karyawan.show', $karyawan))
        ->assertOk()
        ->assertSee('Budi Santoso')
        ->assertSee('Rp 7.000.000')
        ->assertSee('Lihat Absensi')
        ->assertSee(route('absensi.show', $karyawan), false);
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
