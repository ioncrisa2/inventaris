<?php

use App\Models\KomponenGaji;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(adminUser());
});

test('tambah dan edit komponen gaji menggunakan halaman khusus', function () {
    $this->get(route('komponen-gaji.create'))
        ->assertOk()
        ->assertViewIs('komponen-gaji.form')
        ->assertSee('Simpan Komponen')
        ->assertSee('Nominal Tidak Tetap')
        ->assertSee('Nominal Tetap - List')
        ->assertSee('data-component-fixed-list', false)
        ->assertDontSee('Harian (Sehari)');

    $komponen = KomponenGaji::create([
        'nama_komponen' => 'Tunjangan Transport',
        'jenis' => 'Tunjangan',
        'metode_perhitungan' => 'nominal_tetap',
        'nilai_default' => 500000,
    ]);

    $this->get(route('komponen-gaji.edit', $komponen))
        ->assertOk()
        ->assertViewIs('komponen-gaji.form')
        ->assertSee('Simpan Perubahan')
        ->assertSee('Tunjangan Transport');

    $this->get(route('komponen-gaji.index'))
        ->assertOk()
        ->assertSee(route('komponen-gaji.create'), false)
        ->assertSee(route('komponen-gaji.edit', $komponen), false)
        ->assertDontSee('createKomponenGajiModal');
});

test('komponen gaji nominal tetap can be created', function () {
    $this->post(route('komponen-gaji.store'), [
        'nama_komponen' => 'Tunjangan Transport',
        'jenis' => 'Tunjangan',
        'metode_perhitungan' => 'nominal_tetap',
        'nilai_default' => 500000,
    ])->assertRedirect(route('komponen-gaji.index'));

    $this->assertDatabaseHas('komponen_gaji', [
        'nama_komponen' => 'Tunjangan Transport',
        'jenis' => 'Tunjangan',
        'metode_perhitungan' => 'nominal_tetap',
        'dasar_persentase' => null,
    ]);
});

test('komponen gaji persentase menyimpan dasar_persentase otomatis', function () {
    $this->post(route('komponen-gaji.store'), [
        'nama_komponen' => 'Tunjangan Jabatan',
        'jenis' => 'Tunjangan',
        'metode_perhitungan' => 'persentase',
        'nilai_default' => 10,
    ])->assertRedirect(route('komponen-gaji.index'));

    $this->assertDatabaseHas('komponen_gaji', [
        'nama_komponen' => 'Tunjangan Jabatan',
        'metode_perhitungan' => 'persentase',
        'dasar_persentase' => 'gaji_pokok',
    ]);
});

test('nilai persentase harus antara 0 sampai 100', function () {
    $this->post(route('komponen-gaji.store'), [
        'nama_komponen' => 'Tunjangan Tidak Wajar',
        'jenis' => 'Tunjangan',
        'metode_perhitungan' => 'persentase',
        'nilai_default' => 150,
    ])->assertSessionHasErrors('nilai_default');

    $this->assertDatabaseMissing('komponen_gaji', ['nama_komponen' => 'Tunjangan Tidak Wajar']);
});

test('komponen persentase dengan pengali dapat dibuat', function () {
    $this->post(route('komponen-gaji.store'), [
        'nama_komponen' => 'Tunjangan Anak',
        'jenis' => 'Tunjangan',
        'metode_perhitungan' => 'persentase_pengali',
        'nilai_default' => 2,
    ])->assertRedirect(route('komponen-gaji.index'));

    $this->assertDatabaseHas('komponen_gaji', [
        'nama_komponen' => 'Tunjangan Anak',
        'metode_perhitungan' => 'persentase_pengali',
        'nilai_default' => 2,
        'dasar_persentase' => 'gaji_pokok',
    ]);
});

test('metode nominal input transaksi disimpan tanpa nilai default', function () {
    $this->post(route('komponen-gaji.store'), [
        'nama_komponen' => 'Bonus Tidak Tetap',
        'jenis' => 'Tunjangan',
        'metode_perhitungan' => 'nominal_tidak_tetap',
    ])->assertRedirect(route('komponen-gaji.index'));

    $this->assertDatabaseHas('komponen_gaji', [
        'nama_komponen' => 'Bonus Tidak Tetap',
        'metode_perhitungan' => 'nominal_tidak_tetap',
        'nilai_default' => 0,
        'dasar_persentase' => null,
    ]);
});

test('nominal tetap list menyimpan repeater pada master dan dapat diperbarui', function () {
    $this->post(route('komponen-gaji.store'), [
        'nama_komponen' => 'Tunjangan Beras',
        'jenis' => 'Tunjangan',
        'metode_perhitungan' => 'nominal_tetap_list',
        'rincian' => [
            ['keterangan' => 'Laki-laki', 'nominal' => 150000],
            ['keterangan' => 'Perempuan', 'nominal' => 125000],
        ],
    ])->assertRedirect(route('komponen-gaji.index'));

    $komponen = KomponenGaji::where('nama_komponen', 'Tunjangan Beras')->firstOrFail();

    expect((string) $komponen->nilai_default)->toBe('0.00')
        ->and($komponen->rincian()->orderBy('urutan')->pluck('keterangan')->all())
        ->toBe(['Laki-laki', 'Perempuan']);

    $this->assertDatabaseHas('komponen_gaji_rincian', [
        'komponen_gaji_id' => $komponen->id,
        'keterangan' => 'Laki-laki',
        'nominal' => 150000,
        'urutan' => 0,
    ]);

    $this->get(route('komponen-gaji.edit', $komponen))
        ->assertOk()
        ->assertSee('Laki-laki')
        ->assertSee('value="150000.00"', false);

    $this->put(route('komponen-gaji.update', $komponen), [
        'nama_komponen' => 'Tunjangan Beras',
        'jenis' => 'Tunjangan',
        'metode_perhitungan' => 'nominal_tetap_list',
        'rincian' => [
            ['keterangan' => 'Semua Karyawan', 'nominal' => 175000],
        ],
    ])->assertRedirect(route('komponen-gaji.index'));

    expect($komponen->rincian()->count())->toBe(1);
    $this->assertDatabaseHas('komponen_gaji_rincian', [
        'komponen_gaji_id' => $komponen->id,
        'keterangan' => 'Semua Karyawan',
        'nominal' => 175000,
        'urutan' => 0,
    ]);
    $this->assertDatabaseMissing('komponen_gaji_rincian', [
        'komponen_gaji_id' => $komponen->id,
        'keterangan' => 'Laki-laki',
    ]);
});

test('nominal tetap list wajib memiliki rincian master yang lengkap', function () {
    $payload = [
        'nama_komponen' => 'Tunjangan Beras',
        'jenis' => 'Tunjangan',
        'metode_perhitungan' => 'nominal_tetap_list',
    ];

    $this->post(route('komponen-gaji.store'), $payload)
        ->assertSessionHasErrors('rincian');

    $this->post(route('komponen-gaji.store'), [
        ...$payload,
        'rincian' => [
            ['keterangan' => '', 'nominal' => 'tidak-valid'],
        ],
    ])->assertSessionHasErrors([
        'rincian.0.keterangan',
        'rincian.0.nominal',
    ]);

    $this->post(route('komponen-gaji.store'), [
        ...$payload,
        'rincian' => [
            ['keterangan' => 'Laki-laki', 'nominal' => 150000],
            ['keterangan' => ' laki-laki ', 'nominal' => 125000],
        ],
    ])->assertSessionHasErrors('rincian.1.keterangan');

    $this->assertDatabaseMissing('komponen_gaji', ['nama_komponen' => 'Tunjangan Beras']);
});

test('metode harian sehari tidak lagi diterima', function () {
    $this->post(route('komponen-gaji.store'), [
        'nama_komponen' => 'Uang Harian Lama',
        'jenis' => 'Tunjangan',
        'metode_perhitungan' => 'harian_sehari',
        'nilai_default' => 50000,
    ])->assertSessionHasErrors('metode_perhitungan');

    $this->assertDatabaseMissing('komponen_gaji', ['nama_komponen' => 'Uang Harian Lama']);
});

test('nilai nominal tidak boleh negatif', function () {
    $this->post(route('komponen-gaji.store'), [
        'nama_komponen' => 'Potongan Aneh',
        'jenis' => 'Potongan',
        'metode_perhitungan' => 'nominal_tetap',
        'nilai_default' => -1000,
    ])->assertSessionHasErrors('nilai_default');
});

test('nilai komponen di luar DECIMAL 15 2 dan notasi ilmiah ditolak', function () {
    foreach (['10000000000000.00', '1e3'] as $nilai) {
        $this->post(route('komponen-gaji.store'), [
            'nama_komponen' => 'Komponen Tidak Valid '.$nilai,
            'jenis' => 'Tunjangan',
            'metode_perhitungan' => 'nominal_tetap',
            'nilai_default' => $nilai,
        ])->assertSessionHasErrors('nilai_default');
    }

    expect(KomponenGaji::count())->toBe(0);
});

test('komponen gaji can be updated', function () {
    $komponen = KomponenGaji::create([
        'nama_komponen' => 'Tunjangan Jabatan',
        'jenis' => 'Tunjangan',
        'metode_perhitungan' => 'persentase',
        'nilai_default' => 10,
        'dasar_persentase' => 'gaji_pokok',
    ]);

    $this->put(route('komponen-gaji.update', $komponen), [
        'nama_komponen' => 'Tunjangan Jabatan',
        'jenis' => 'Tunjangan',
        'metode_perhitungan' => 'nominal_tetap',
        'nilai_default' => 750000,
    ])->assertRedirect(route('komponen-gaji.index'));

    $this->assertDatabaseHas('komponen_gaji', [
        'id' => $komponen->id,
        'metode_perhitungan' => 'nominal_tetap',
        'dasar_persentase' => null,
    ]);
});

test('komponen gaji can be deleted', function () {
    $komponen = KomponenGaji::create([
        'nama_komponen' => 'Tunjangan Transport',
        'jenis' => 'Tunjangan',
        'metode_perhitungan' => 'nominal_tetap',
        'nilai_default' => 500000,
    ]);

    $this->delete(route('komponen-gaji.destroy', $komponen))
        ->assertRedirect(route('komponen-gaji.index'));

    $this->assertDatabaseMissing('komponen_gaji', ['id' => $komponen->id]);
});
