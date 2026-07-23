<?php

use App\Models\Absensi;
use App\Models\Karyawan;
use App\Models\KomponenGaji;
use App\Models\TransaksiGaji;
use App\Models\TransaksiGajiDetail;
use App\Models\UnitKerja;
use App\Repositories\AbsensiRepository;
use App\Repositories\KaryawanRepository;
use App\Repositories\KomponenGajiRepository;
use App\Repositories\TransaksiGajiRepository;
use App\Rules\Decimal15Two;
use App\Services\TransaksiGajiService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(adminUser());

    $this->karyawan = Karyawan::create([
        'nik' => 'EMP-001',
        'nama_lengkap' => 'Budi Santoso',
        'tanggal_lahir' => '1990-01-01',
        'unit_kerja_id' => UnitKerja::create(['nama_unit' => 'IT'])->id,
        'jabatan' => 'Staf IT',
        'status_karyawan' => 'Tetap',
        'gaji_pokok' => 5000000,
    ]);

    $this->tunjanganJabatan = KomponenGaji::create([
        'nama_komponen' => 'Tunjangan Jabatan',
        'jenis' => 'Tunjangan',
        'metode_perhitungan' => 'persentase',
        'nilai_default' => 10,
        'dasar_persentase' => 'gaji_pokok',
    ]);

    $this->potonganBpjs = KomponenGaji::create([
        'nama_komponen' => 'Potongan BPJS',
        'jenis' => 'Potongan',
        'metode_perhitungan' => 'nominal_tetap',
        'nilai_default' => 100000,
    ]);
});

function payloadGaji($karyawan, $tunjangan, $potongan, array $override = []): array
{
    // Baris master tidak lagi mengirim metode_perhitungan/nilai: nilainya
    // selalu dikunci dari Komponen Gaji saat ini (lihat siapkanBaris()),
    // jadi cukup "pakai" untuk menandai komponen ini dipilih di transaksi ini.
    return array_merge([
        'karyawan_id' => $karyawan->id,
        'bulan' => 7,
        'tahun' => 2026,
        'baris' => [
            "master_{$tunjangan->id}" => ['pakai' => '1'],
            "master_{$potongan->id}" => ['pakai' => '1'],
        ],
    ], $override);
}

test('transaksi gaji dihitung sesuai rumus: gaji_pokok + tunjangan - potongan', function () {
    // Gaji pokok Rp5.000.000, tunjangan jabatan 10% = Rp500.000, potongan BPJS Rp100.000.
    // gaji_bersih = 5.000.000 + 500.000 - 100.000 = 5.400.000
    $response = $this->post(route('transaksi-gaji.store'), payloadGaji(
        $this->karyawan,
        $this->tunjanganJabatan,
        $this->potonganBpjs,
    ));

    $transaksi = TransaksiGaji::first();
    $response->assertRedirect(route('transaksi-gaji.show', $transaksi));

    expect((string) $transaksi->gaji_pokok)->toBe('5000000.00');
    expect((string) $transaksi->gaji_bersih)->toBe('5400000.00');

    $this->assertDatabaseHas('transaksi_gaji_detail', [
        'transaksi_gaji_id' => $transaksi->id,
        'komponen_gaji_id' => $this->tunjanganJabatan->id,
        'metode_perhitungan_snapshot' => 'persentase',
        'nominal_hasil' => 500000,
    ]);

    $this->assertDatabaseHas('transaksi_gaji_detail', [
        'transaksi_gaji_id' => $transaksi->id,
        'komponen_gaji_id' => $this->potonganBpjs->id,
        'metode_perhitungan_snapshot' => 'nominal_tetap',
        'nominal_hasil' => 100000,
    ]);
});

test('nominal_hasil kiriman client diabaikan, backend menghitung ulang', function () {
    $response = $this->post(route('transaksi-gaji.store'), payloadGaji(
        $this->karyawan,
        $this->tunjanganJabatan,
        $this->potonganBpjs,
        [
            'baris' => [
                "master_{$this->tunjanganJabatan->id}" => [
                    'pakai' => '1',
                    'nominal_hasil' => '999999999', // seharusnya tidak dipakai backend
                ],
            ],
        ],
    ));

    $transaksi = TransaksiGaji::first();
    $response->assertRedirect(route('transaksi-gaji.show', $transaksi));

    $this->assertDatabaseHas('transaksi_gaji_detail', [
        'transaksi_gaji_id' => $transaksi->id,
        'nominal_hasil' => 500000,
    ]);
    $this->assertDatabaseMissing('transaksi_gaji_detail', ['nominal_hasil' => 999999999]);
});

test('metode_perhitungan dan nilai kiriman client untuk baris master diabaikan, selalu ikut Komponen Gaji', function () {
    // Kirim metode & nilai yang sama sekali berbeda dari master (persentase 10%
    // -> coba kirim nominal_tetap 999.999.999); backend harus tetap memakai
    // metode & nilai dari Komponen Gaji, bukan yang dikirim client.
    $response = $this->post(route('transaksi-gaji.store'), payloadGaji(
        $this->karyawan,
        $this->tunjanganJabatan,
        $this->potonganBpjs,
        [
            'baris' => [
                "master_{$this->tunjanganJabatan->id}" => [
                    'pakai' => '1',
                    'metode_perhitungan' => 'nominal_tetap',
                    'nilai' => '999999999',
                ],
            ],
        ],
    ));

    $transaksi = TransaksiGaji::first();
    $response->assertRedirect(route('transaksi-gaji.show', $transaksi));

    $this->assertDatabaseHas('transaksi_gaji_detail', [
        'transaksi_gaji_id' => $transaksi->id,
        'komponen_gaji_id' => $this->tunjanganJabatan->id,
        'metode_perhitungan_snapshot' => 'persentase',
        'nilai_snapshot' => 10,
        'nominal_hasil' => 500000,
    ]);
});

test('satu karyawan hanya boleh punya satu transaksi per bulan', function () {
    $this->post(route('transaksi-gaji.store'), payloadGaji($this->karyawan, $this->tunjanganJabatan, $this->potonganBpjs));

    $this->post(route('transaksi-gaji.store'), payloadGaji($this->karyawan, $this->tunjanganJabatan, $this->potonganBpjs))
        ->assertSessionHasErrors('karyawan_id');

    expect(TransaksiGaji::count())->toBe(1);
});

test('duplicate key dari database diterjemahkan menjadi validation error', function () {
    $exception = (new UniqueConstraintViolationException(
        'sqlite',
        'insert into transaksi_gaji',
        [],
        new PDOException('UNIQUE constraint failed'),
    ))->setColumns(['karyawan_id', 'bulan', 'tahun']);

    $repository = Mockery::mock(TransaksiGajiRepository::class)->makePartial();
    $repository->shouldReceive('conflictingPeriodForUpdate')->once()->andReturnNull();
    $repository->shouldReceive('create')->once()->andThrow($exception);

    $service = new TransaksiGajiService(
        $repository,
        app(KomponenGajiRepository::class),
        app(KaryawanRepository::class),
        app(AbsensiRepository::class),
    );

    try {
        $service->store(
            ['karyawan_id' => $this->karyawan->id, 'bulan' => 7, 'tahun' => 2026],
            ["master_{$this->tunjanganJabatan->id}" => ['pakai' => '1']],
        );

        $this->fail('Pelanggaran unique seharusnya menjadi ValidationException.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('karyawan_id');
    }

    expect(TransaksiGaji::count())->toBe(0);
});

test('service menolak master yang hilang dan melakukan rollback atomik', function () {
    expect(fn () => app(TransaksiGajiService::class)->store(
        ['karyawan_id' => $this->karyawan->id, 'bulan' => 7, 'tahun' => 2026],
        ['master_999999' => ['pakai' => '1']],
    ))->toThrow(ValidationException::class);

    expect(TransaksiGaji::count())->toBe(0);
    expect(TransaksiGajiDetail::count())->toBe(0);
});

test('batas maksimum DECIMAL 15 2 dikenali tanpa perbandingan float', function () {
    expect(Decimal15Two::normalizeNonNegative('9999999999999.99'))->toBe('9999999999999.99');
    expect(Decimal15Two::normalizeNonNegative('10000000000000.00'))->toBeNull();
    expect(Decimal15Two::fitsSigned('-9999999999999.99'))->toBeTrue();
    expect(Decimal15Two::fitsSigned('-10000000000000.00'))->toBeFalse();
});

test('agregat gaji di atas kapasitas database ditolak atomik', function () {
    $this->karyawan->update(['gaji_pokok' => 5000000]);
    $tunjanganSatu = KomponenGaji::create([
        'nama_komponen' => 'Tunjangan Ekstrem Satu',
        'jenis' => 'Tunjangan',
        'metode_perhitungan' => 'nominal_tetap',
        'nilai_default' => '6000000000000.00',
    ]);
    $tunjanganDua = KomponenGaji::create([
        'nama_komponen' => 'Tunjangan Ekstrem Dua',
        'jenis' => 'Tunjangan',
        'metode_perhitungan' => 'nominal_tetap',
        'nilai_default' => '6000000000000.00',
    ]);

    $this->post(route('transaksi-gaji.store'), [
        'karyawan_id' => $this->karyawan->id,
        'bulan' => 7,
        'tahun' => 2026,
        'baris' => [
            "master_{$tunjanganSatu->id}" => ['pakai' => '1'],
            "master_{$tunjanganDua->id}" => ['pakai' => '1'],
        ],
    ])->assertSessionHasErrors('baris');

    expect(TransaksiGaji::count())->toBe(0);
    expect(TransaksiGajiDetail::count())->toBe(0);
});

test('hasil per kehadiran yang melebihi kapasitas database ditolak atomik', function () {
    $this->karyawan->update(['gaji_pokok' => 0]);
    $uangHarian = KomponenGaji::create([
        'nama_komponen' => 'Uang Harian Ekstrem',
        'jenis' => 'Tunjangan',
        'metode_perhitungan' => 'per_kehadiran',
        'nilai_default' => '9999999999999.99',
    ]);
    Absensi::create(['karyawan_id' => $this->karyawan->id, 'tanggal' => '2026-07-01', 'status' => 'Hadir']);
    Absensi::create(['karyawan_id' => $this->karyawan->id, 'tanggal' => '2026-07-02', 'status' => 'Hadir']);

    $this->post(route('transaksi-gaji.store'), [
        'karyawan_id' => $this->karyawan->id,
        'bulan' => 7,
        'tahun' => 2026,
        'baris' => ["master_{$uangHarian->id}" => ['pakai' => '1']],
    ])->assertSessionHasErrors("baris.master_{$uangHarian->id}.nilai");

    expect(TransaksiGaji::count())->toBe(0);
    expect(TransaksiGajiDetail::count())->toBe(0);
});

test('total potongan negatif di luar kapasitas database ditolak atomik', function () {
    $this->karyawan->update(['gaji_pokok' => 0]);
    $potonganSatu = KomponenGaji::create([
        'nama_komponen' => 'Potongan Ekstrem Satu',
        'jenis' => 'Potongan',
        'metode_perhitungan' => 'nominal_tetap',
        'nilai_default' => '6000000000000.00',
    ]);
    $potonganDua = KomponenGaji::create([
        'nama_komponen' => 'Potongan Ekstrem Dua',
        'jenis' => 'Potongan',
        'metode_perhitungan' => 'nominal_tetap',
        'nilai_default' => '6000000000000.00',
    ]);

    $this->post(route('transaksi-gaji.store'), [
        'karyawan_id' => $this->karyawan->id,
        'bulan' => 7,
        'tahun' => 2026,
        'baris' => [
            "master_{$potonganSatu->id}" => ['pakai' => '1'],
            "master_{$potonganDua->id}" => ['pakai' => '1'],
        ],
    ])->assertSessionHasErrors('baris');

    expect(TransaksiGaji::count())->toBe(0);
    expect(TransaksiGajiDetail::count())->toBe(0);
});

test('snapshot transaksi tidak berubah walau master komponen gaji diedit setelahnya', function () {
    $this->post(route('transaksi-gaji.store'), payloadGaji($this->karyawan, $this->tunjanganJabatan, $this->potonganBpjs));

    $transaksi = TransaksiGaji::with('details')->first();
    $gajiBersihSebelum = (string) $transaksi->gaji_bersih;

    // Ubah nilai_default master setelah transaksi tersimpan.
    $this->tunjanganJabatan->update(['nilai_default' => 50]);
    $this->potonganBpjs->update(['nilai_default' => 1000000]);

    $transaksi->refresh();
    $transaksi->load('details');

    expect((string) $transaksi->gaji_bersih)->toBe($gajiBersihSebelum);

    $detailTunjangan = $transaksi->details->firstWhere('komponen_gaji_id', $this->tunjanganJabatan->id);
    expect((string) $detailTunjangan->nominal_hasil)->toBe('500000.00');
    expect((string) $detailTunjangan->nilai_snapshot)->toBe('10.00');
});

test('minimal satu komponen harus dipilih', function () {
    $this->post(route('transaksi-gaji.store'), [
        'karyawan_id' => $this->karyawan->id,
        'bulan' => 7,
        'tahun' => 2026,
        'baris' => [
            "master_{$this->tunjanganJabatan->id}" => [
                'pakai' => '',
                'metode_perhitungan' => 'persentase',
                'nilai' => '10',
            ],
        ],
    ])->assertSessionHasErrors('baris');

    expect(TransaksiGaji::count())->toBe(0);
});

test('slip gaji bisa dicetak dengan rincian komponen', function () {
    $this->post(route('transaksi-gaji.store'), payloadGaji($this->karyawan, $this->tunjanganJabatan, $this->potonganBpjs));
    $transaksi = TransaksiGaji::first();

    $this->get(route('transaksi-gaji.cetak', $transaksi))
        ->assertOk()
        ->assertSee('Slip Gaji')
        ->assertSee('Budi Santoso')
        ->assertSee('Tunjangan Jabatan')
        ->assertSee('Potongan BPJS')
        ->assertSee('5.400.000');
});

test('tunjangan per kehadiran dihitung dari jumlah hari berstatus hadir saja', function () {
    $uangMakan = KomponenGaji::create([
        'nama_komponen' => 'Tunjangan Uang Makan',
        'jenis' => 'Tunjangan',
        'metode_perhitungan' => 'per_kehadiran',
        'nilai_default' => 30000,
        'dasar_persentase' => null,
    ]);

    foreach (range(1, 20) as $hari) {
        Absensi::create([
            'karyawan_id' => $this->karyawan->id,
            'tanggal' => sprintf('2026-07-%02d', $hari),
            'status' => 'Hadir',
        ]);
    }
    Absensi::create(['karyawan_id' => $this->karyawan->id, 'tanggal' => '2026-07-21', 'status' => 'Izin']);
    Absensi::create(['karyawan_id' => $this->karyawan->id, 'tanggal' => '2026-07-22', 'status' => 'Sakit']);
    Absensi::create(['karyawan_id' => $this->karyawan->id, 'tanggal' => '2026-07-23', 'status' => 'Alpha']);

    $response = $this->post(route('transaksi-gaji.store'), [
        'karyawan_id' => $this->karyawan->id,
        'bulan' => 7,
        'tahun' => 2026,
        'baris' => [
            "master_{$uangMakan->id}" => ['pakai' => '1'],
        ],
    ]);

    $transaksi = TransaksiGaji::first();
    $response->assertRedirect(route('transaksi-gaji.show', $transaksi));

    // Gaji pokok 5.000.000 + (20 hari hadir x Rp30.000) = 5.600.000; Izin/Sakit/Alpha tidak dihitung.
    expect((string) $transaksi->gaji_bersih)->toBe('5600000.00');

    $this->assertDatabaseHas('transaksi_gaji_detail', [
        'transaksi_gaji_id' => $transaksi->id,
        'komponen_gaji_id' => $uangMakan->id,
        'metode_perhitungan_snapshot' => 'per_kehadiran',
        'nilai_snapshot' => 30000,
        'jumlah_hadir_snapshot' => 20,
        'nominal_hasil' => 600000,
    ]);
});

test('tunjangan per kehadiran memakai periode gajian 25 bulan lalu s.d. 24 bulan ini, bukan kalender bulan', function () {
    $uangMakan = KomponenGaji::create([
        'nama_komponen' => 'Tunjangan Uang Makan',
        'jenis' => 'Tunjangan',
        'metode_perhitungan' => 'per_kehadiran',
        'nilai_default' => 30000,
        'dasar_persentase' => null,
    ]);

    // Periode "Juli 2026" = 25 Juni s.d. 24 Juli.
    Absensi::create(['karyawan_id' => $this->karyawan->id, 'tanggal' => '2026-06-24', 'status' => 'Hadir']); // di luar periode (sebelum mulai)
    Absensi::create(['karyawan_id' => $this->karyawan->id, 'tanggal' => '2026-06-25', 'status' => 'Hadir']); // batas awal, ikut terhitung
    Absensi::create(['karyawan_id' => $this->karyawan->id, 'tanggal' => '2026-07-24', 'status' => 'Hadir']); // batas akhir, ikut terhitung
    Absensi::create(['karyawan_id' => $this->karyawan->id, 'tanggal' => '2026-07-25', 'status' => 'Hadir']); // di luar periode (sudah masuk periode Agustus)

    $response = $this->post(route('transaksi-gaji.store'), [
        'karyawan_id' => $this->karyawan->id,
        'bulan' => 7,
        'tahun' => 2026,
        'baris' => [
            "master_{$uangMakan->id}" => ['pakai' => '1'],
        ],
    ]);

    $transaksi = TransaksiGaji::first();
    $response->assertRedirect(route('transaksi-gaji.show', $transaksi));

    $this->assertDatabaseHas('transaksi_gaji_detail', [
        'transaksi_gaji_id' => $transaksi->id,
        'komponen_gaji_id' => $uangMakan->id,
        'jumlah_hadir_snapshot' => 2,
        'nominal_hasil' => 60000,
    ]);
});

test('tunjangan per kehadiran bernilai nol jika belum ada absensi tercatat', function () {
    $uangMakan = KomponenGaji::create([
        'nama_komponen' => 'Tunjangan Uang Makan',
        'jenis' => 'Tunjangan',
        'metode_perhitungan' => 'per_kehadiran',
        'nilai_default' => 30000,
        'dasar_persentase' => null,
    ]);

    $response = $this->post(route('transaksi-gaji.store'), [
        'karyawan_id' => $this->karyawan->id,
        'bulan' => 7,
        'tahun' => 2026,
        'baris' => [
            "master_{$uangMakan->id}" => ['pakai' => '1'],
        ],
    ]);

    $transaksi = TransaksiGaji::first();
    $response->assertRedirect(route('transaksi-gaji.show', $transaksi));

    expect((string) $transaksi->gaji_bersih)->toBe('5000000.00');

    $this->assertDatabaseHas('transaksi_gaji_detail', [
        'transaksi_gaji_id' => $transaksi->id,
        'komponen_gaji_id' => $uangMakan->id,
        'jumlah_hadir_snapshot' => 0,
        'nominal_hasil' => 0,
    ]);
});

test('transaksi gaji bisa diedit, komponen dan periode diperbarui', function () {
    $this->post(route('transaksi-gaji.store'), payloadGaji($this->karyawan, $this->tunjanganJabatan, $this->potonganBpjs));
    $transaksi = TransaksiGaji::first();

    // Edit: lepas potongan BPJS, ganti bulan.
    $response = $this->put(route('transaksi-gaji.update', $transaksi), [
        'karyawan_id' => $this->karyawan->id,
        'bulan' => 8,
        'tahun' => 2026,
        'baris' => [
            "master_{$this->tunjanganJabatan->id}" => ['pakai' => '1'],
        ],
    ]);

    $transaksi->refresh();
    $transaksi->load('details');
    $response->assertRedirect(route('transaksi-gaji.show', $transaksi));

    expect($transaksi->bulan)->toBe(8);
    expect((string) $transaksi->gaji_bersih)->toBe('5500000.00');
    expect($transaksi->details)->toHaveCount(1);
});

test('service menolak update ke periode yang sudah digunakan tanpa mengubah data lama', function () {
    $service = app(TransaksiGajiService::class);
    $baris = ["master_{$this->tunjanganJabatan->id}" => ['pakai' => '1']];
    $juli = $service->store(
        ['karyawan_id' => $this->karyawan->id, 'bulan' => 7, 'tahun' => 2026],
        $baris,
    );
    $agustus = $service->store(
        ['karyawan_id' => $this->karyawan->id, 'bulan' => 8, 'tahun' => 2026],
        $baris,
    );

    expect(fn () => $service->update(
        $juli,
        ['karyawan_id' => $this->karyawan->id, 'bulan' => 8, 'tahun' => 2026],
        $baris,
    ))->toThrow(ValidationException::class);

    expect($juli->refresh()->bulan)->toBe(7);
    expect($agustus->refresh()->bulan)->toBe(8);
    expect(TransaksiGaji::count())->toBe(2);
    expect(TransaksiGajiDetail::count())->toBe(2);
});

test('transaksi gaji menolak master komponen yang tidak ada', function () {
    $this->from(route('transaksi-gaji.create'))
        ->post(route('transaksi-gaji.store'), [
            'karyawan_id' => $this->karyawan->id,
            'bulan' => 7,
            'tahun' => 2026,
            'baris' => [
                'master_999999' => ['pakai' => '1'],
            ],
        ])
        ->assertRedirect(route('transaksi-gaji.create'))
        ->assertSessionHasErrors(['baris.master_999999', 'baris']);

    expect(TransaksiGaji::count())->toBe(0);
});

test('transaksi gaji baru menolak custom detail palsu', function () {
    $this->from(route('transaksi-gaji.create'))
        ->post(route('transaksi-gaji.store'), [
            'karyawan_id' => $this->karyawan->id,
            'bulan' => 7,
            'tahun' => 2026,
            'baris' => [
                'custom_999999' => [
                    'pakai' => '1',
                    'metode_perhitungan' => 'nominal_tetap',
                    'nilai' => 1000000,
                    'nama_komponen_snapshot' => 'Komponen Palsu',
                    'jenis_snapshot' => 'Potongan',
                ],
            ],
        ])
        ->assertRedirect(route('transaksi-gaji.create'))
        ->assertSessionHasErrors(['baris.custom_999999', 'baris']);

    expect(TransaksiGaji::count())->toBe(0);
});

test('payload baris transaksi gaji yang rusak divalidasi dan tidak memicu error server', function () {
    $this->from(route('transaksi-gaji.create'))
        ->post(route('transaksi-gaji.store'), [
            'karyawan_id' => $this->karyawan->id,
            'bulan' => 7,
            'tahun' => 2026,
            'baris' => ['master_1' => 'bukan-array'],
        ])
        ->assertRedirect(route('transaksi-gaji.create'))
        ->assertSessionHasErrors(['baris.master_1', 'baris']);

    expect(TransaksiGaji::count())->toBe(0);
});

test('container baris transaksi gaji yang bukan array ditolak tanpa error server', function () {
    foreach (['bukan-array', null] as $baris) {
        $this->from(route('transaksi-gaji.create'))
            ->post(route('transaksi-gaji.store'), [
                'karyawan_id' => $this->karyawan->id,
                'bulan' => 7,
                'tahun' => 2026,
                'baris' => $baris,
            ])
            ->assertRedirect(route('transaksi-gaji.create'))
            ->assertSessionHasErrors('baris');
    }

    expect(TransaksiGaji::count())->toBe(0);
});

test('legacy orphan detail menolak nilai notasi ilmiah yang tidak didukung BCMath', function () {
    $transaksi = TransaksiGaji::create([
        'karyawan_id' => $this->karyawan->id,
        'bulan' => 7,
        'tahun' => 2026,
        'gaji_pokok' => 5000000,
        'gaji_bersih' => 5100000,
    ]);
    $detail = TransaksiGajiDetail::create([
        'transaksi_gaji_id' => $transaksi->id,
        'komponen_gaji_id' => null,
        'nama_komponen_snapshot' => 'Tunjangan Legacy',
        'jenis_snapshot' => 'Tunjangan',
        'metode_perhitungan_snapshot' => 'nominal_tetap',
        'nilai_snapshot' => 100000,
        'nominal_hasil' => 100000,
    ]);

    $this->from(route('transaksi-gaji.edit', $transaksi))
        ->put(route('transaksi-gaji.update', $transaksi), [
            'karyawan_id' => $this->karyawan->id,
            'bulan' => 7,
            'tahun' => 2026,
            'baris' => [
                "custom_{$detail->id}" => [
                    'pakai' => '1',
                    'metode_perhitungan' => 'nominal_tetap',
                    'nilai' => '1e3',
                ],
            ],
        ])
        ->assertRedirect(route('transaksi-gaji.edit', $transaksi))
        ->assertSessionHasErrors("baris.custom_{$detail->id}.nilai");

    expect((string) $detail->refresh()->nilai_snapshot)->toBe('100000.00');
});

test('legacy orphan detail hanya bisa diperbarui oleh transaksi pemilik dan snapshot tidak dapat ditamper', function () {
    $transaksi = TransaksiGaji::create([
        'karyawan_id' => $this->karyawan->id,
        'bulan' => 7,
        'tahun' => 2026,
        'gaji_pokok' => 5000000,
        'gaji_bersih' => 5100000,
    ]);
    $detail = TransaksiGajiDetail::create([
        'transaksi_gaji_id' => $transaksi->id,
        'komponen_gaji_id' => null,
        'nama_komponen_snapshot' => 'Tunjangan Legacy',
        'jenis_snapshot' => 'Tunjangan',
        'metode_perhitungan_snapshot' => 'nominal_tetap',
        'nilai_snapshot' => 100000,
        'nominal_hasil' => 100000,
    ]);

    $this->put(route('transaksi-gaji.update', $transaksi), [
        'karyawan_id' => $this->karyawan->id,
        'bulan' => 7,
        'tahun' => 2026,
        'baris' => [
            "custom_{$detail->id}" => [
                'pakai' => '1',
                'metode_perhitungan' => 'nominal_tetap',
                'nilai' => 250000,
                'nama_komponen_snapshot' => 'Nama Hasil Manipulasi',
                'jenis_snapshot' => 'Potongan',
            ],
        ],
    ])->assertRedirect(route('transaksi-gaji.show', $transaksi));

    $this->assertDatabaseHas('transaksi_gaji_detail', [
        'transaksi_gaji_id' => $transaksi->id,
        'nama_komponen_snapshot' => 'Tunjangan Legacy',
        'jenis_snapshot' => 'Tunjangan',
        'nilai_snapshot' => 250000,
        'nominal_hasil' => 250000,
    ]);
    $this->assertDatabaseMissing('transaksi_gaji_detail', [
        'transaksi_gaji_id' => $transaksi->id,
        'nama_komponen_snapshot' => 'Nama Hasil Manipulasi',
    ]);
});

test('karyawan_id tidak bisa diganti lewat edit walau dikirim karyawan lain', function () {
    $karyawanLain = Karyawan::create([
        'nik' => 'EMP-002',
        'nama_lengkap' => 'Siti Rahma',
        'tanggal_lahir' => '1992-01-01',
        'unit_kerja_id' => $this->karyawan->unit_kerja_id,
        'jabatan' => 'Staf Keuangan',
        'status_karyawan' => 'Tetap',
        'gaji_pokok' => 9000000,
    ]);

    $this->post(route('transaksi-gaji.store'), payloadGaji($this->karyawan, $this->tunjanganJabatan, $this->potonganBpjs));
    $transaksi = TransaksiGaji::first();

    $response = $this->put(route('transaksi-gaji.update', $transaksi), [
        'karyawan_id' => $karyawanLain->id, // seharusnya diabaikan backend
        'bulan' => 7,
        'tahun' => 2026,
        'baris' => [
            "master_{$this->tunjanganJabatan->id}" => ['pakai' => '1'],
            "master_{$this->potonganBpjs->id}" => ['pakai' => '1'],
        ],
    ]);

    $transaksi->refresh();
    $response->assertRedirect(route('transaksi-gaji.show', $transaksi));

    expect($transaksi->karyawan_id)->toBe($this->karyawan->id);
    expect((string) $transaksi->gaji_pokok)->toBe('5000000.00');
    expect((string) $transaksi->gaji_bersih)->toBe('5400000.00');
    $this->assertDatabaseMissing('transaksi_gaji', ['karyawan_id' => $karyawanLain->id]);
});

test('transaksi gaji can be viewed and deleted', function () {
    $this->post(route('transaksi-gaji.store'), payloadGaji($this->karyawan, $this->tunjanganJabatan, $this->potonganBpjs));
    $transaksi = TransaksiGaji::first();

    $this->get(route('transaksi-gaji.show', $transaksi))
        ->assertOk()
        ->assertSee('Budi Santoso')
        ->assertSee('Tunjangan Jabatan')
        ->assertSee('Potongan BPJS');

    $this->delete(route('transaksi-gaji.destroy', $transaksi))
        ->assertRedirect(route('transaksi-gaji.index'));

    $this->assertDatabaseMissing('transaksi_gaji', ['id' => $transaksi->id]);
    $this->assertDatabaseMissing('transaksi_gaji_detail', ['transaksi_gaji_id' => $transaksi->id]);
});

test('form only shows the component management link once', function () {
    $response = $this->get(route('transaksi-gaji.create'))->assertOk();

    expect(substr_count($response->getContent(), 'Ubah di Komponen Gaji'))->toBe(1);
});

test('detail groups allowances before deductions and separates the salary summary', function () {
    $this->post(route('transaksi-gaji.store'), payloadGaji(
        $this->karyawan,
        $this->tunjanganJabatan,
        $this->potonganBpjs,
    ));

    $transaksi = TransaksiGaji::firstOrFail();

    $this->get(route('transaksi-gaji.show', $transaksi))
        ->assertOk()
        ->assertSeeInOrder(['Tunjangan', 'Tunjangan Jabatan', 'Potongan', 'Potongan BPJS'])
        ->assertSee('payroll-component-group', false)
        ->assertSee('payroll-summary', false)
        ->assertDontSee('<tfoot>', false);
});
