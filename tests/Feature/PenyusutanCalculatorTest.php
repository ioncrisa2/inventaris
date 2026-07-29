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

test('metode selain garis lurus belum didukung', function () {
    config(['inventaris.metode_penyusutan_default' => 'saldo_menurun']);

    expect(fn () => PenyusutanCalculator::hitungTahunan('Bukan Bangunan - Kelompok 1', '4800000', Carbon::parse('2020-01-01'), 2020))
        ->toThrow(InvalidArgumentException::class);
});
