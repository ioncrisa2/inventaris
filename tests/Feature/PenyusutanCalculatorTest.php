<?php

use App\Support\PenyusutanCalculator;
use Illuminate\Support\Carbon;

test('masa manfaat tahun sesuai golongan UU PPh', function () {
    expect(PenyusutanCalculator::masaManfaatTahun('Bukan Bangunan - Kelompok 1'))->toBe(4);
    expect(PenyusutanCalculator::masaManfaatTahun('Bukan Bangunan - Kelompok 2'))->toBe(8);
    expect(PenyusutanCalculator::masaManfaatTahun('Bukan Bangunan - Kelompok 3'))->toBe(16);
    expect(PenyusutanCalculator::masaManfaatTahun('Bukan Bangunan - Kelompok 4'))->toBe(20);
    expect(PenyusutanCalculator::masaManfaatTahun('Bangunan - Permanen'))->toBe(20);
    expect(PenyusutanCalculator::masaManfaatTahun('Bangunan - Bukan Permanen'))->toBe(10);
});

test('metode default adalah garis lurus', function () {
    expect(PenyusutanCalculator::metode())->toBe('garis_lurus');
});

test('tarif penyusutan mengikuti tabel PMK 72 tahun 2023', function () {
    expect(PenyusutanCalculator::tarifTahunan('Bukan Bangunan - Kelompok 1', 'garis_lurus'))->toBe('0.25');
    expect(PenyusutanCalculator::tarifTahunan('Bukan Bangunan - Kelompok 2', 'garis_lurus'))->toBe('0.125');
    expect(PenyusutanCalculator::tarifTahunan('Bukan Bangunan - Kelompok 3', 'garis_lurus'))->toBe('0.0625');
    expect(PenyusutanCalculator::tarifTahunan('Bukan Bangunan - Kelompok 4', 'garis_lurus'))->toBe('0.05');
    expect(PenyusutanCalculator::tarifTahunan('Bukan Bangunan - Kelompok 1', 'saldo_menurun'))->toBe('0.50');
    expect(PenyusutanCalculator::tarifTahunan('Bukan Bangunan - Kelompok 2', 'saldo_menurun'))->toBe('0.25');
    expect(PenyusutanCalculator::tarifTahunan('Bukan Bangunan - Kelompok 3', 'saldo_menurun'))->toBe('0.125');
    expect(PenyusutanCalculator::tarifTahunan('Bukan Bangunan - Kelompok 4', 'saldo_menurun'))->toBe('0.10');
    expect(PenyusutanCalculator::tarifTahunan('Bangunan - Permanen', 'garis_lurus'))->toBe('0.05');
    expect(PenyusutanCalculator::tarifTahunan('Bangunan - Bukan Permanen', 'garis_lurus'))->toBe('0.10');
});

test('penyusutan tahun perolehan (mulai januari) dihitung setahun penuh', function () {
    // Kelompok 1 = 4 tahun (48 bulan). Rp4.800.000 / 48 = Rp100.000/bulan pas.
    $hasil = PenyusutanCalculator::hitungTahunan(
        'Bukan Bangunan - Kelompok 1',
        '4800000',
        Carbon::parse('2020-01-15'),
        2020,
    );

    expect($hasil['metode'])->toBe('garis_lurus');
    expect($hasil['masa_manfaat_tahun'])->toBe(4);
    expect($hasil['akumulasi_awal_tahun'])->toBe('0.00');
    expect($hasil['penyusutan_tahun_ini'])->toBe('1200000.00');
    expect($hasil['akumulasi_akhir_tahun'])->toBe('1200000.00');
    expect($hasil['nilai_buku_akhir_tahun'])->toBe('3600000.00');
});

test('penyusutan tahun terakhir masa manfaat menghabiskan sisa nilai buku', function () {
    $hasil = PenyusutanCalculator::hitungTahunan(
        'Bukan Bangunan - Kelompok 1',
        '4800000',
        Carbon::parse('2020-01-15'),
        2023,
    );

    expect($hasil['akumulasi_awal_tahun'])->toBe('3600000.00');
    expect($hasil['penyusutan_tahun_ini'])->toBe('1200000.00');
    expect($hasil['akumulasi_akhir_tahun'])->toBe('4800000.00');
    expect($hasil['nilai_buku_akhir_tahun'])->toBe('0.00');
});

test('perolehan pertengahan tahun diprorata per bulan sejak bulan perolehan', function () {
    // Perolehan Juli 2020: hanya 6 bulan (Jul-Des) yang disusutkan di tahun 2020.
    $hasil = PenyusutanCalculator::hitungTahunan(
        'Bukan Bangunan - Kelompok 1',
        '4800000',
        Carbon::parse('2020-07-01'),
        2020,
    );

    expect($hasil['penyusutan_tahun_ini'])->toBe('600000.00');
    expect($hasil['akumulasi_akhir_tahun'])->toBe('600000.00');
});

test('masa manfaat berakhir pertengahan tahun tetap menghabiskan sisa nilai buku', function () {
    // Perolehan Juli 2020, 48 bulan berakhir Juni 2024 (bukan Desember).
    $hasil = PenyusutanCalculator::hitungTahunan(
        'Bukan Bangunan - Kelompok 1',
        '4800000',
        Carbon::parse('2020-07-01'),
        2024,
    );

    expect($hasil['akumulasi_akhir_tahun'])->toBe('4800000.00');
    expect($hasil['nilai_buku_akhir_tahun'])->toBe('0.00');
});

test('aset yang sudah lewat masa manfaat sebelum tahun laporan tetap tampil dengan nilai buku nol', function () {
    $hasil = PenyusutanCalculator::hitungTahunan(
        'Bukan Bangunan - Kelompok 1',
        '4800000',
        Carbon::parse('2010-01-01'),
        2020,
    );

    expect($hasil['akumulasi_awal_tahun'])->toBe('4800000.00');
    expect($hasil['penyusutan_tahun_ini'])->toBe('0.00');
    expect($hasil['akumulasi_akhir_tahun'])->toBe('4800000.00');
    expect($hasil['nilai_buku_akhir_tahun'])->toBe('0.00');
});

test('aset yang belum diperoleh pada tahun laporan belum disusutkan sama sekali', function () {
    $hasil = PenyusutanCalculator::hitungTahunan(
        'Bukan Bangunan - Kelompok 1',
        '4800000',
        Carbon::parse('2025-06-01'),
        2024,
    );

    expect($hasil['penyusutan_tahun_ini'])->toBe('0.00');
    expect($hasil['akumulasi_akhir_tahun'])->toBe('0.00');
    expect($hasil['nilai_buku_akhir_tahun'])->toBe('4800000.00');
});

test('tahun akhir masa manfaat dihitung dari bulan perolehan', function () {
    expect(PenyusutanCalculator::tahunAkhirMasaManfaat('Bukan Bangunan - Kelompok 1', Carbon::parse('2020-01-15')))->toBe(2023);
    expect(PenyusutanCalculator::tahunAkhirMasaManfaat('Bukan Bangunan - Kelompok 1', Carbon::parse('2020-07-01')))->toBe(2024);
});

test('jadwal tahunan mencakup seluruh tahun dari perolehan sampai akhir masa manfaat', function () {
    $jadwal = PenyusutanCalculator::jadwalTahunan('Bukan Bangunan - Kelompok 1', '4800000', Carbon::parse('2020-01-15'));

    expect(array_keys($jadwal))->toBe([2020, 2021, 2022, 2023]);
    expect($jadwal[2023]['nilai_buku_akhir_tahun'])->toBe('0.00');
});

test('golongan tanpa masa manfaat yang dikenal menolak dihitung', function () {
    expect(fn () => PenyusutanCalculator::hitungTahunan('Golongan Tidak Dikenal', '1000000', Carbon::parse('2020-01-01'), 2020))
        ->toThrow(InvalidArgumentException::class);
});

test('saldo menurun dihitung dari nilai buku dan sisa nilai dibebankan di akhir masa manfaat', function () {
    config(['inventaris.metode_penyusutan_default' => 'saldo_menurun']);

    $tahunPertama = PenyusutanCalculator::hitungTahunan(
        'Bukan Bangunan - Kelompok 1',
        '4800000',
        Carbon::parse('2020-01-01'),
        2020,
    );
    $tahunTerakhir = PenyusutanCalculator::hitungTahunan(
        'Bukan Bangunan - Kelompok 1',
        '4800000',
        Carbon::parse('2020-01-01'),
        2023,
    );

    expect($tahunPertama['metode'])->toBe('saldo_menurun')
        ->and($tahunPertama['penyusutan_tahun_ini'])->toBe('2400000.00')
        ->and($tahunPertama['nilai_buku_akhir_tahun'])->toBe('2400000.00')
        ->and($tahunTerakhir['penyusutan_tahun_ini'])->toBe('600000.00')
        ->and($tahunTerakhir['nilai_buku_akhir_tahun'])->toBe('0.00');
});

test('saldo menurun diprorata pada tahun perolehan pertengahan tahun', function () {
    config(['inventaris.metode_penyusutan_default' => 'saldo_menurun']);

    $hasil = PenyusutanCalculator::hitungTahunan(
        'Bukan Bangunan - Kelompok 1',
        '4800000',
        Carbon::parse('2020-07-01'),
        2020,
    );

    expect($hasil['penyusutan_tahun_ini'])->toBe('1200000.00')
        ->and($hasil['nilai_buku_akhir_tahun'])->toBe('3600000.00');
});

test('bangunan tetap memakai garis lurus ketika kebijakan bukan bangunan memakai saldo menurun', function () {
    config(['inventaris.metode_penyusutan_default' => 'saldo_menurun']);

    $hasil = PenyusutanCalculator::hitungTahunan(
        'Bangunan - Permanen',
        '240000000',
        Carbon::parse('2020-01-01'),
        2020,
    );

    expect($hasil['metode'])->toBe('garis_lurus')
        ->and($hasil['penyusutan_tahun_ini'])->toBe('12000000.00');
});

test('metode yang tidak dikenal ditolak', function () {
    config(['inventaris.metode_penyusutan_default' => 'metode_tidak_dikenal']);

    expect(fn () => PenyusutanCalculator::hitungTahunan('Bukan Bangunan - Kelompok 1', '4800000', Carbon::parse('2020-01-01'), 2020))
        ->toThrow(InvalidArgumentException::class);
});
