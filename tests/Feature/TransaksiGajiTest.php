<?php

use App\Models\Karyawan;
use App\Models\KomponenGaji;
use App\Models\TemplateSlipGaji;
use App\Models\TransaksiGaji;
use App\Models\TransaksiGajiDetail;
use App\Models\UnitKerja;
use App\Models\User;
use App\Repositories\KaryawanRepository;
use App\Repositories\KomponenGajiRepository;
use App\Repositories\TransaksiGajiRepository;
use App\Rules\Decimal15Two;
use App\Services\HariOperasionalService;
use App\Services\TransaksiGajiService;
use App\Support\SlipGajiPaperLayout;
use App\Support\SlipGajiTemplateSchema;
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

function buatPenandaTanganSlip(
    Karyawan $karyawanAcuan,
    string $nik,
    string $nama,
    string $jabatan,
    ?string $tanggalMengundurkanDiri = null,
): Karyawan {
    return Karyawan::create([
        'nik' => $nik,
        'nama_lengkap' => $nama,
        'tanggal_lahir' => '1990-01-01',
        'unit_kerja_id' => $karyawanAcuan->unit_kerja_id,
        'jabatan' => $jabatan,
        'status_karyawan' => 'Tetap',
        'gaji_pokok' => 5000000,
        'tanggal_mengundurkan_diri' => $tanggalMengundurkanDiri,
    ]);
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
        app(HariOperasionalService::class),
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

test('hasil per hari yang melebihi kapasitas database ditolak atomik', function () {
    $this->karyawan->update(['gaji_pokok' => 0]);
    $uangHarian = KomponenGaji::create([
        'nama_komponen' => 'Uang Harian Ekstrem',
        'jenis' => 'Tunjangan',
        'metode_perhitungan' => 'per_hari',
        'nilai_default' => '9999999999999.99',
    ]);

    $this->post(route('transaksi-gaji.store'), [
        'karyawan_id' => $this->karyawan->id,
        'bulan' => 7,
        'tahun' => 2026,
        'baris' => [
            "master_{$uangHarian->id}" => [
                'pakai' => '1',
                'tanggal_awal' => '2026-07-01',
                'tanggal_akhir' => '2026-07-02',
            ],
        ],
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
    $dibuatOleh = buatPenandaTanganSlip($this->karyawan, 'EMP-SIGN-01', 'Siti Pembuat', 'Bendahara');
    $mengetahui = buatPenandaTanganSlip($this->karyawan, 'EMP-SIGN-02', 'Rina Mengetahui', 'Kepala Keuangan');

    $response = $this->get(route('transaksi-gaji.cetak', [
        'transaksiGaji' => $transaksi,
        'dibuat_oleh_id' => $dibuatOleh->id,
        'mengetahui_id' => $mengetahui->id,
    ]))
        ->assertOk()
        ->assertViewIs('transaksi-gaji.cetak')
        ->assertViewHas('paperLayout', SlipGajiPaperLayout::LEFT_RIGHT)
        ->assertSee('page-layout--f4-portrait', false)
        ->assertSee('salary-slip-sheet--left-right', false)
        ->assertDontSee('salary-slip-sheet--single', false)
        ->assertSee('Slip Gaji')
        ->assertSee('Budi Santoso')
        ->assertSee('Tunjangan Jabatan')
        ->assertSee('Potongan BPJS')
        ->assertSee('Dibuat oleh')
        ->assertSee('Siti Pembuat')
        ->assertSee('Mengetahui')
        ->assertSee('Rina Mengetahui')
        ->assertSee('Diterima oleh')
        ->assertDontSee('<table', false)
        ->assertSee('5.400.000');

    expect(strpos($response->getContent(), 'Tunjangan'))
        ->toBeLessThan(strpos($response->getContent(), 'Potongan'));
});

test('modal cetak menjelaskan dua arah pembagian kertas tanpa istilah ambigu', function () {
    $this->post(route('transaksi-gaji.store'), payloadGaji($this->karyawan, $this->tunjanganJabatan, $this->potonganBpjs));
    $transaksi = TransaksiGaji::firstOrFail();

    $this->get(route('transaksi-gaji.show', $transaksi))
        ->assertOk()
        ->assertSee('name="paper_layout"', false)
        ->assertSee('value="left_right"', false)
        ->assertSee('value="top_bottom"', false)
        ->assertSee('Kiri–kanan')
        ->assertSee('Garis pembagi vertikal')
        ->assertSee('Atas–bawah')
        ->assertSee('Garis pembagi horizontal');
});

test('layout terbit menjadi default dan dapat dioverride hanya untuk satu cetakan', function () {
    $configuration = SlipGajiTemplateSchema::default();
    $configuration['page']['paper_layout'] = SlipGajiPaperLayout::TOP_BOTTOM;

    $this->post(route('pengaturan.slip-gaji.publish'), [
        'configuration' => json_encode($configuration),
        'expected_revision' => 1,
    ])->assertRedirect();

    $this->post(route('transaksi-gaji.store'), payloadGaji($this->karyawan, $this->tunjanganJabatan, $this->potonganBpjs));
    $transaksi = TransaksiGaji::firstOrFail();
    $dibuatOleh = buatPenandaTanganSlip($this->karyawan, 'EMP-SIGN-12', 'Siti Pembuat', 'Bendahara');
    $mengetahui = buatPenandaTanganSlip($this->karyawan, 'EMP-SIGN-13', 'Rina Mengetahui', 'Kepala Keuangan');
    $parameters = [
        'transaksiGaji' => $transaksi,
        'dibuat_oleh_id' => $dibuatOleh->id,
        'mengetahui_id' => $mengetahui->id,
    ];

    $this->get(route('transaksi-gaji.cetak', $parameters))
        ->assertOk()
        ->assertViewHas('paperLayout', SlipGajiPaperLayout::TOP_BOTTOM)
        ->assertSee('salary-slip-sheet--top-bottom', false);

    $this->get(route('transaksi-gaji.cetak', [
        ...$parameters,
        'paper_layout' => SlipGajiPaperLayout::LEFT_RIGHT,
    ]))
        ->assertOk()
        ->assertViewHas('paperLayout', SlipGajiPaperLayout::LEFT_RIGHT)
        ->assertSee('salary-slip-sheet--left-right', false);

    expect(TemplateSlipGaji::firstOrFail()->konfigurasi_terbit['page']['paper_layout'])
        ->toBe(SlipGajiPaperLayout::TOP_BOTTOM);
});

test('cetak slip hanya memakai template terbit dan meng-escape teks statis', function () {
    $configuration = SlipGajiTemplateSchema::default();
    $textBlock = $configuration['blocks'][7];
    $textBlock['id'] = 'catatan-slip';
    $textBlock['type'] = 'text';
    $textBlock['content'] = '<script>alert("gaji")</script>';
    $textBlock['variant'] = 'default';
    $configuration['blocks'][] = $textBlock;

    $this->post(route('pengaturan.slip-gaji.draft'), [
        'configuration' => json_encode($configuration),
        'expected_revision' => 1,
    ])->assertRedirect();

    $this->post(route('transaksi-gaji.store'), payloadGaji($this->karyawan, $this->tunjanganJabatan, $this->potonganBpjs));
    $transaksi = TransaksiGaji::firstOrFail();
    $dibuatOleh = buatPenandaTanganSlip($this->karyawan, 'EMP-SIGN-10', 'Siti Pembuat', 'Bendahara');
    $mengetahui = buatPenandaTanganSlip($this->karyawan, 'EMP-SIGN-11', 'Rina Mengetahui', 'Kepala Keuangan');
    $printUrl = route('transaksi-gaji.cetak', [
        'transaksiGaji' => $transaksi,
        'dibuat_oleh_id' => $dibuatOleh->id,
        'mengetahui_id' => $mengetahui->id,
    ]);

    $this->get($printUrl)
        ->assertOk()
        ->assertDontSee('catatan-slip')
        ->assertDontSee('<script>alert("gaji")</script>', false);

    $this->post(route('pengaturan.slip-gaji.publish'), [
        'configuration' => json_encode($configuration),
        'expected_revision' => 2,
    ])->assertRedirect();

    $this->get($printUrl)
        ->assertOk()
        ->assertSee('&lt;script&gt;alert(&quot;gaji&quot;)&lt;/script&gt;', false)
        ->assertDontSee('<script>alert("gaji")</script>', false);
});

test('cetak slip menolak penanda tangan kosong sama atau tidak aktif', function () {
    $this->post(route('transaksi-gaji.store'), payloadGaji($this->karyawan, $this->tunjanganJabatan, $this->potonganBpjs));
    $transaksi = TransaksiGaji::firstOrFail();
    $aktif = buatPenandaTanganSlip($this->karyawan, 'EMP-SIGN-03', 'Penanda Aktif', 'Bendahara');
    $tidakAktif = buatPenandaTanganSlip(
        $this->karyawan,
        'EMP-SIGN-04',
        'Penanda Tidak Aktif',
        'Mantan Kepala',
        '2026-01-01',
    );

    $this->from(route('transaksi-gaji.show', $transaksi))
        ->get(route('transaksi-gaji.cetak', ['transaksiGaji' => $transaksi]))
        ->assertRedirect(route('transaksi-gaji.show', $transaksi))
        ->assertSessionHasErrors(['dibuat_oleh_id', 'mengetahui_id']);

    $this->from(route('transaksi-gaji.show', $transaksi))
        ->get(route('transaksi-gaji.cetak', [
            'transaksiGaji' => $transaksi,
            'dibuat_oleh_id' => $aktif->id,
            'mengetahui_id' => $aktif->id,
        ]))
        ->assertRedirect(route('transaksi-gaji.show', $transaksi))
        ->assertSessionHasErrors(['dibuat_oleh_id', 'mengetahui_id']);

    $this->from(route('transaksi-gaji.show', $transaksi))
        ->get(route('transaksi-gaji.cetak', [
            'transaksiGaji' => $transaksi,
            'dibuat_oleh_id' => $aktif->id,
            'mengetahui_id' => $tidakAktif->id,
        ]))
        ->assertRedirect(route('transaksi-gaji.show', $transaksi))
        ->assertSessionHasErrors('mengetahui_id');

    $this->from(route('transaksi-gaji.show', $transaksi))
        ->get(route('transaksi-gaji.cetak', [
            'transaksiGaji' => $transaksi,
            'dibuat_oleh_id' => $aktif->id,
            'mengetahui_id' => buatPenandaTanganSlip(
                $this->karyawan,
                'EMP-SIGN-14',
                'Penanda Kedua',
                'Kepala Keuangan',
            )->id,
            'paper_layout' => 'vertical',
        ]))
        ->assertRedirect(route('transaksi-gaji.show', $transaksi))
        ->assertSessionHasErrors('paper_layout');
});

test('cetak massal semua karyawan hanya mengambil transaksi pada periode yang dipilih', function () {
    $karyawanKedua = buatPenandaTanganSlip($this->karyawan, 'EMP-002', 'Ani Karyawan', 'Staf IT');
    $karyawanKetiga = buatPenandaTanganSlip($this->karyawan, 'EMP-003', 'Citra Karyawan', 'Staf IT');

    foreach ([$this->karyawan, $karyawanKedua, $karyawanKetiga] as $karyawan) {
        $this->post(route('transaksi-gaji.store'), payloadGaji(
            $karyawan,
            $this->tunjanganJabatan,
            $this->potonganBpjs,
        ))->assertRedirect();
    }

    $this->post(route('transaksi-gaji.store'), payloadGaji(
        $this->karyawan,
        $this->tunjanganJabatan,
        $this->potonganBpjs,
        ['bulan' => 6],
    ))->assertRedirect();

    $dibuatOleh = buatPenandaTanganSlip($this->karyawan, 'EMP-SIGN-05', 'Dewi Bendahara', 'Bendahara');
    $mengetahui = buatPenandaTanganSlip($this->karyawan, 'EMP-SIGN-06', 'Eko Kepala', 'Kepala Keuangan');
    $parameters = [
        'mode' => 'periode',
        'bulan' => 7,
        'tahun' => 2026,
        'dibuat_oleh_id' => $dibuatOleh->id,
        'mengetahui_id' => $mengetahui->id,
    ];

    $response = $this->get(route('transaksi-gaji.cetak-massal', $parameters))
        ->assertOk()
        ->assertSee('salary-slip-sheet--left-right', false)
        ->assertSee('Slip Gaji Massal')
        ->assertSee('3 slip')
        ->assertSee('2 lembar F4')
        ->assertSeeInOrder(['Ani Karyawan', 'Budi Santoso', 'Citra Karyawan'])
        ->assertViewHas('slipPages', function ($pages) {
            return $pages->count() === 2
                && $pages->first()->count() === 2
                && $pages->last()->count() === 1
                && $pages->flatten(1)->every(
                    fn ($slip) => $slip['transaksi']->bulan === 7
                        && $slip['transaksi']->tahun === 2026,
                );
        });

    expect(substr_count($response->getContent(), 'class="salary-slip '))
        ->toBe(3);

    $this->get(route('transaksi-gaji.cetak-massal', [
        ...$parameters,
        'paper_layout' => SlipGajiPaperLayout::TOP_BOTTOM,
    ]))
        ->assertOk()
        ->assertSee('salary-slip-sheet--top-bottom', false)
        ->assertViewHas('slipPages', fn ($pages) => $pages->map->count()->all() === [2, 1]);
});

test('cetak massal satu karyawan mengambil rentang periode inklusif lintas tahun', function () {
    $karyawanLain = buatPenandaTanganSlip($this->karyawan, 'EMP-OTHER', 'Ani Karyawan', 'Staf IT');

    foreach ([
        [$this->karyawan, 12, 2025],
        [$this->karyawan, 1, 2026],
        [$this->karyawan, 3, 2026],
        [$karyawanLain, 1, 2026],
    ] as [$karyawan, $bulan, $tahun]) {
        TransaksiGaji::create([
            'karyawan_id' => $karyawan->id,
            'bulan' => $bulan,
            'tahun' => $tahun,
            'gaji_pokok' => 5000000,
            'gaji_bersih' => 5000000,
        ]);
    }

    $dibuatOleh = buatPenandaTanganSlip($this->karyawan, 'EMP-SIGN-07', 'Dewi Bendahara', 'Bendahara');
    $mengetahui = buatPenandaTanganSlip($this->karyawan, 'EMP-SIGN-08', 'Eko Kepala', 'Kepala Keuangan');

    $this->get(route('transaksi-gaji.cetak-massal', [
        'mode' => 'karyawan',
        'karyawan_id' => $this->karyawan->id,
        'periode_awal' => '2025-12',
        'periode_akhir' => '2026-01',
        'dibuat_oleh_id' => $dibuatOleh->id,
        'mengetahui_id' => $mengetahui->id,
    ]))
        ->assertOk()
        ->assertSee('2 slip')
        ->assertSee('Budi Santoso')
        ->assertDontSee('Ani Karyawan')
        ->assertViewHas('slipPages', function ($pages) {
            return $pages->flatten(1)
                ->map(fn ($slip) => [
                    $slip['transaksi']->tahun,
                    $slip['transaksi']->bulan,
                ])
                ->all() === [[2025, 12], [2026, 1]];
        });
});

test('filter cetak massal menolak field silang rentang terbalik dan hasil kosong', function () {
    $dibuatOleh = buatPenandaTanganSlip($this->karyawan, 'EMP-SIGN-09', 'Dewi Bendahara', 'Bendahara');
    $mengetahui = buatPenandaTanganSlip($this->karyawan, 'EMP-SIGN-15', 'Eko Kepala', 'Kepala Keuangan');
    $signers = [
        'dibuat_oleh_id' => $dibuatOleh->id,
        'mengetahui_id' => $mengetahui->id,
    ];

    $this->from(route('transaksi-gaji.index'))
        ->get(route('transaksi-gaji.cetak-massal', [
            ...$signers,
            'mode' => 'periode',
            'tahun' => 2026,
            'karyawan_id' => $this->karyawan->id,
        ]))
        ->assertRedirect(route('transaksi-gaji.index'))
        ->assertSessionHasErrors(['bulan', 'karyawan_id']);

    $this->from(route('transaksi-gaji.index'))
        ->get(route('transaksi-gaji.cetak-massal', [
            ...$signers,
            'mode' => 'karyawan',
            'karyawan_id' => $this->karyawan->id,
            'periode_awal' => '2026-07',
            'periode_akhir' => '2026-06',
        ]))
        ->assertRedirect(route('transaksi-gaji.index'))
        ->assertSessionHasErrors('periode_akhir');

    $this->from(route('transaksi-gaji.index'))
        ->get(route('transaksi-gaji.cetak-massal', [
            ...$signers,
            'mode' => 'karyawan',
            'karyawan_id' => $this->karyawan->id,
            'periode_awal' => '1999-12',
            'periode_akhir' => '2101-01',
        ]))
        ->assertRedirect(route('transaksi-gaji.index'))
        ->assertSessionHasErrors(['periode_awal', 'periode_akhir']);

    $this->from(route('transaksi-gaji.index'))
        ->get(route('transaksi-gaji.cetak-massal', [
            ...$signers,
            'mode' => 'periode',
            'bulan' => 1,
            'tahun' => 2099,
        ]))
        ->assertRedirect(route('transaksi-gaji.index'))
        ->assertSessionHasErrors('mode');
});

test('cetak massal meminta filter dipersempit jika hasil melebihi seratus slip', function () {
    $now = now();
    $karyawanRows = collect(range(1, 101))->map(fn ($index) => [
        'nik' => "EMP-MASSAL-{$index}",
        'nama_lengkap' => "Karyawan Massal {$index}",
        'tanggal_lahir' => '1990-01-01',
        'unit_kerja_id' => $this->karyawan->unit_kerja_id,
        'jabatan' => 'Staf',
        'status_karyawan' => 'Tetap',
        'gaji_pokok' => 5000000,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    Karyawan::insert($karyawanRows->all());

    $targetIds = Karyawan::query()
        ->where('nik', 'like', 'EMP-MASSAL-%')
        ->pluck('id');
    TransaksiGaji::insert($targetIds->map(fn ($karyawanId) => [
        'karyawan_id' => $karyawanId,
        'bulan' => 8,
        'tahun' => 2026,
        'gaji_pokok' => 5000000,
        'gaji_bersih' => 5000000,
        'created_at' => $now,
        'updated_at' => $now,
    ])->all());

    $dibuatOleh = buatPenandaTanganSlip($this->karyawan, 'EMP-SIGN-16', 'Dewi Bendahara', 'Bendahara');
    $mengetahui = buatPenandaTanganSlip($this->karyawan, 'EMP-SIGN-17', 'Eko Kepala', 'Kepala Keuangan');

    $this->from(route('transaksi-gaji.index'))
        ->get(route('transaksi-gaji.cetak-massal', [
            'mode' => 'periode',
            'bulan' => 8,
            'tahun' => 2026,
            'dibuat_oleh_id' => $dibuatOleh->id,
            'mengetahui_id' => $mengetahui->id,
        ]))
        ->assertRedirect(route('transaksi-gaji.index'))
        ->assertSessionHasErrors('mode');
});

test('slip dengan rincian panjang memakai satu lembar penuh agar tanda tangan tidak tertimpa', function () {
    $this->post(route('transaksi-gaji.store'), payloadGaji($this->karyawan, $this->tunjanganJabatan, $this->potonganBpjs));
    $transaksi = TransaksiGaji::firstOrFail();

    foreach (range(1, 12) as $index) {
        TransaksiGajiDetail::create([
            'transaksi_gaji_id' => $transaksi->id,
            'komponen_gaji_id' => null,
            'nama_komponen_snapshot' => "Tunjangan Tambahan Panjang Nomor {$index}",
            'jenis_snapshot' => 'Tunjangan',
            'metode_perhitungan_snapshot' => 'nominal_tetap',
            'nilai_snapshot' => 10000,
            'nominal_hasil' => 10000,
        ]);
    }

    $transaksi->load('details');
    $service = app(TransaksiGajiService::class);
    $leftRightPages = $service->slipPrintPages(
        collect([$transaksi]),
        SlipGajiPaperLayout::LEFT_RIGHT,
    );
    $topBottomPages = $service->slipPrintPages(
        collect([$transaksi]),
        SlipGajiPaperLayout::TOP_BOTTOM,
    );

    foreach ([$leftRightPages, $topBottomPages] as $pages) {
        expect($pages)->toHaveCount(1)
            ->and($pages->first())->toHaveCount(1)
            ->and($pages->first()->first()['is_full_page'])->toBeTrue();
    }
});

test('tunjangan per hari dihitung dari jumlah hari operasional (bukan kalender) pada rentang tanggal yang diinput manual', function () {
    $uangMakan = KomponenGaji::create([
        'nama_komponen' => 'Tunjangan Uang Makan',
        'jenis' => 'Tunjangan',
        'metode_perhitungan' => 'per_hari',
        'nilai_default' => 30000,
        'dasar_persentase' => null,
    ]);

    $response = $this->post(route('transaksi-gaji.store'), [
        'karyawan_id' => $this->karyawan->id,
        'bulan' => 7,
        'tahun' => 2026,
        'baris' => [
            "master_{$uangMakan->id}" => [
                'pakai' => '1',
                'tanggal_awal' => '2026-07-01',
                'tanggal_akhir' => '2026-07-20',
            ],
        ],
    ]);

    $transaksi = TransaksiGaji::first();
    $response->assertRedirect(route('transaksi-gaji.show', $transaksi));

    // 2026-07-01 s.d. 2026-07-20 = 20 hari kalender, tapi ada 3 hari Minggu
    // (5, 12, 19) yang bukan hari operasional (default Senin-Sabtu), jadi
    // hanya 17 hari yang dihitung: 5.000.000 + (17 x Rp30.000) = 5.510.000.
    expect((string) $transaksi->gaji_bersih)->toBe('5510000.00');

    $this->assertDatabaseHas('transaksi_gaji_detail', [
        'transaksi_gaji_id' => $transaksi->id,
        'komponen_gaji_id' => $uangMakan->id,
        'metode_perhitungan_snapshot' => 'per_hari',
        'nilai_snapshot' => 30000,
        'jumlah_hari_snapshot' => 17,
        'nominal_hasil' => 510000,
    ]);

    $detail = $transaksi->details()->where('komponen_gaji_id', $uangMakan->id)->first();
    expect($detail->tanggal_awal_snapshot->toDateString())->toBe('2026-07-01');
    expect($detail->tanggal_akhir_snapshot->toDateString())->toBe('2026-07-20');
});

test('tunjangan per hari bernilai nol jika seluruh rentang tanggal jatuh pada hari libur', function () {
    $uangMakan = KomponenGaji::create([
        'nama_komponen' => 'Tunjangan Uang Makan',
        'jenis' => 'Tunjangan',
        'metode_perhitungan' => 'per_hari',
        'nilai_default' => 30000,
        'dasar_persentase' => null,
    ]);

    $response = $this->post(route('transaksi-gaji.store'), [
        'karyawan_id' => $this->karyawan->id,
        'bulan' => 7,
        'tahun' => 2026,
        'baris' => [
            "master_{$uangMakan->id}" => [
                'pakai' => '1',
                // 2026-07-19 adalah hari Minggu (libur secara default).
                'tanggal_awal' => '2026-07-19',
                'tanggal_akhir' => '2026-07-19',
            ],
        ],
    ]);

    $transaksi = TransaksiGaji::first();
    $response->assertRedirect(route('transaksi-gaji.show', $transaksi));

    expect((string) $transaksi->gaji_bersih)->toBe('5000000.00');

    $this->assertDatabaseHas('transaksi_gaji_detail', [
        'transaksi_gaji_id' => $transaksi->id,
        'komponen_gaji_id' => $uangMakan->id,
        'jumlah_hari_snapshot' => 0,
        'nominal_hasil' => 0,
    ]);
});

test('tunjangan per hari dengan tanggal awal dan akhir sama dihitung sebagai satu hari', function () {
    $uangMakan = KomponenGaji::create([
        'nama_komponen' => 'Tunjangan Uang Makan',
        'jenis' => 'Tunjangan',
        'metode_perhitungan' => 'per_hari',
        'nilai_default' => 30000,
        'dasar_persentase' => null,
    ]);

    $response = $this->post(route('transaksi-gaji.store'), [
        'karyawan_id' => $this->karyawan->id,
        'bulan' => 7,
        'tahun' => 2026,
        'baris' => [
            "master_{$uangMakan->id}" => [
                'pakai' => '1',
                'tanggal_awal' => '2026-07-10',
                'tanggal_akhir' => '2026-07-10',
            ],
        ],
    ]);

    $transaksi = TransaksiGaji::first();
    $response->assertRedirect(route('transaksi-gaji.show', $transaksi));

    $this->assertDatabaseHas('transaksi_gaji_detail', [
        'transaksi_gaji_id' => $transaksi->id,
        'komponen_gaji_id' => $uangMakan->id,
        'jumlah_hari_snapshot' => 1,
        'nominal_hasil' => 30000,
    ]);
});

test('tunjangan per hari yang dicentang tanpa rentang tanggal valid ditolak validasi', function () {
    $uangMakan = KomponenGaji::create([
        'nama_komponen' => 'Tunjangan Uang Makan',
        'jenis' => 'Tunjangan',
        'metode_perhitungan' => 'per_hari',
        'nilai_default' => 30000,
        'dasar_persentase' => null,
    ]);

    $this->from(route('transaksi-gaji.create'))
        ->post(route('transaksi-gaji.store'), [
            'karyawan_id' => $this->karyawan->id,
            'bulan' => 7,
            'tahun' => 2026,
            'baris' => [
                "master_{$uangMakan->id}" => ['pakai' => '1'],
            ],
        ])
        ->assertRedirect(route('transaksi-gaji.create'))
        ->assertSessionHasErrors([
            "baris.master_{$uangMakan->id}.tanggal_awal",
            "baris.master_{$uangMakan->id}.tanggal_akhir",
        ]);

    expect(TransaksiGaji::count())->toBe(0);

    $this->from(route('transaksi-gaji.create'))
        ->post(route('transaksi-gaji.store'), [
            'karyawan_id' => $this->karyawan->id,
            'bulan' => 7,
            'tahun' => 2026,
            'baris' => [
                "master_{$uangMakan->id}" => [
                    'pakai' => '1',
                    'tanggal_awal' => '2026-07-20',
                    'tanggal_akhir' => '2026-07-10',
                ],
            ],
        ])
        ->assertRedirect(route('transaksi-gaji.create'))
        ->assertSessionHasErrors("baris.master_{$uangMakan->id}.tanggal_akhir");

    expect(TransaksiGaji::count())->toBe(0);
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
        ->assertRedirect(route('transaksi-gaji.karyawan', ['karyawan' => $transaksi->karyawan_id]));

    $this->assertDatabaseMissing('transaksi_gaji', ['id' => $transaksi->id]);
    $this->assertDatabaseMissing('transaksi_gaji_detail', ['transaksi_gaji_id' => $transaksi->id]);
});

test('index displays employees clustered with a transaction count, not one row per transaction', function () {
    $this->post(route('transaksi-gaji.store'), payloadGaji($this->karyawan, $this->tunjanganJabatan, $this->potonganBpjs, [
        'bulan' => 6,
        'tahun' => 2026,
    ]));
    $this->post(route('transaksi-gaji.store'), payloadGaji($this->karyawan, $this->tunjanganJabatan, $this->potonganBpjs, [
        'bulan' => 7,
        'tahun' => 2026,
    ]));

    $this->get(route('transaksi-gaji.index'))
        ->assertOk()
        ->assertSee($this->karyawan->nama_lengkap)
        ->assertSee(route('transaksi-gaji.karyawan', $this->karyawan), false)
        ->assertSee('Cetak Slip Massal')
        ->assertSee('data-bs-target="#cetakSlipGajiMassalModal"', false)
        ->assertSee(route('transaksi-gaji.cetak-massal'), false)
        ->assertSee('name="mode"', false)
        ->assertSee('value="periode"', false)
        ->assertSee('value="karyawan"', false)
        ->assertSee('name="periode_awal"', false)
        ->assertSee('name="periode_akhir"', false)
        ->assertDontSee('data-bulk-select=', false)
        // Satu baris per karyawan (bukan per transaksi), dengan jumlah transaksinya.
        ->assertViewHas('karyawanList', function ($karyawanList) {
            $baris = $karyawanList->firstWhere('id', $this->karyawan->id);

            return $karyawanList->count() === 1 && $baris->transaksi_gaji_count === 2;
        });
});

test('karyawan detail page lists every transaction for that employee only', function () {
    $karyawanLain = Karyawan::create([
        'nik' => 'EMP-LAIN',
        'nama_lengkap' => 'Karyawan Lain',
        'tanggal_lahir' => '1990-01-01',
        'unit_kerja_id' => $this->karyawan->unit_kerja_id,
        'jabatan' => 'Staf',
        'status_karyawan' => 'Tetap',
        'gaji_pokok' => 4000000,
    ]);

    $this->post(route('transaksi-gaji.store'), payloadGaji($this->karyawan, $this->tunjanganJabatan, $this->potonganBpjs, [
        'bulan' => 6,
        'tahun' => 2026,
    ]));
    $this->post(route('transaksi-gaji.store'), payloadGaji($this->karyawan, $this->tunjanganJabatan, $this->potonganBpjs, [
        'bulan' => 7,
        'tahun' => 2026,
    ]));
    $this->post(route('transaksi-gaji.store'), [
        'karyawan_id' => $karyawanLain->id,
        'bulan' => 7,
        'tahun' => 2026,
        'baris' => ["master_{$this->tunjanganJabatan->id}" => ['pakai' => '1']],
    ]);
    $transaksiKaryawanLain = TransaksiGaji::query()
        ->where('karyawan_id', $karyawanLain->id)
        ->firstOrFail();

    $response = $this->get(route('transaksi-gaji.karyawan', $this->karyawan))
        ->assertOk()
        ->assertSee('Transaksi Gaji — '.$this->karyawan->nama_lengkap)
        ->assertSee('Juni 2026')
        ->assertSee('Juli 2026')
        ->assertSee('data-bulk-select-all="transaksi-gaji"', false)
        ->assertSee('data-bulk-select="transaksi-gaji"', false)
        ->assertSee('Hapus Terpilih')
        ->assertDontSee('Cetak Slip Terpilih')
        ->assertDontSee('id="cetakSlipGajiMassalModal"', false)
        ->assertDontSee(route('transaksi-gaji.cetak-massal'), false)
        ->assertDontSee(route('transaksi-gaji.show', $transaksiKaryawanLain), false);

    expect(substr_count($response->getContent(), 'data-slip-print-modal'))
        ->toBe(1);
});

test('viewer tanpa izin hapus tetap dapat mencetak individual tanpa melihat bulk selection', function () {
    $this->post(route('transaksi-gaji.store'), payloadGaji(
        $this->karyawan,
        $this->tunjanganJabatan,
        $this->potonganBpjs,
    ));
    $transaksi = TransaksiGaji::firstOrFail();

    $this->actingAs(staffUser());

    $this->get(route('transaksi-gaji.karyawan', $this->karyawan))
        ->assertOk()
        ->assertSee('Cetak slip gaji periode Juli 2026')
        ->assertSee('id="cetakSlipGajiModal"', false)
        ->assertDontSee('data-bulk-select-all="transaksi-gaji"', false)
        ->assertDontSee('data-bulk-select="transaksi-gaji"', false)
        ->assertDontSee('Cetak Slip Terpilih')
        ->assertDontSee(route('transaksi-gaji.cetak-massal'), false)
        ->assertDontSee('Hapus Terpilih')
        ->assertDontSee('data-bulk-delete-trigger', false)
        ->assertDontSee('data-delete-url', false)
        ->assertDontSee(route('transaksi-gaji.create'), false)
        ->assertDontSee(route('transaksi-gaji.edit', $transaksi), false);
});

test('viewer transaksi tetap melihat modal cetak massal tanpa izin laporan penggajian', function () {
    TransaksiGaji::create([
        'karyawan_id' => $this->karyawan->id,
        'bulan' => 7,
        'tahun' => 2026,
        'gaji_pokok' => 5000000,
        'gaji_bersih' => 5000000,
    ]);
    $viewer = User::factory()->create();
    $viewer->givePermissionTo('transaksi-gaji.view');
    $this->actingAs($viewer);

    $this->get(route('transaksi-gaji.index'))
        ->assertOk()
        ->assertSee('Cetak Slip Massal')
        ->assertSee('id="cetakSlipGajiMassalModal"', false)
        ->assertSee(route('transaksi-gaji.cetak-massal'), false)
        ->assertDontSee(route('laporan.penggajian'), false)
        ->assertDontSee(route('transaksi-gaji.create'), false);
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
