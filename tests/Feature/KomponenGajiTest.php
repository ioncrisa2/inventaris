<?php

use App\Models\KomponenGaji;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(adminUser());
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
