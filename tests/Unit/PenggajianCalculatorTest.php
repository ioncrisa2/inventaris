<?php

use App\Support\PenggajianCalculator;

test('nominal tetap dipakai apa adanya', function () {
    expect(PenggajianCalculator::hitungNominal('nominal_tetap', '500000', '7000000'))->toBe('500000.00');
});

test('persentase dihitung dari gaji pokok', function () {
    // Rp5.000.000 x 10% = Rp500.000, sesuai contoh pada spesifikasi.
    expect(PenggajianCalculator::hitungNominal('persentase', '10', '5000000'))->toBe('500000.00');
});

test('pembulatan persentase memakai round half up ke 2 desimal', function () {
    // 33.33% x 100 = 33.33 pas, tidak ada pecahan sen.
    expect(PenggajianCalculator::hitungNominal('persentase', '33.33', '100'))->toBe('33.33');

    // 12345 x 1% = 123.45 pas.
    expect(PenggajianCalculator::hitungNominal('persentase', '1', '12345'))->toBe('123.45');

    // Hasil tepat x.xx5 harus dibulatkan ke atas (round half up), bukan dipotong.
    // 0.5% x 2001 = 10.005 -> dibulatkan jadi 10.01, bukan 10.00.
    expect(PenggajianCalculator::hitungNominal('persentase', '0.5', '2001'))->toBe('10.01');
});

test('nominal tetap dengan pecahan sen dibulatkan round half up', function () {
    expect(PenggajianCalculator::hitungNominal('nominal_tetap', '10.005', null))->toBe('10.01');
});

test('gaji pokok null dianggap nol untuk persentase', function () {
    expect(PenggajianCalculator::hitungNominal('persentase', '10', null))->toBe('0.00');
});

test('persentase dengan pengali dihitung dari gaji pokok lalu dikalikan jumlah', function () {
    // Tunjangan anak 2% per anak: Rp5.000.000 x 2% x 3 anak = Rp300.000.
    expect(PenggajianCalculator::hitungNominal('persentase_pengali', '2', '5000000', null, 3))->toBe('300000.00');
});

test('persentase dengan pengali null menghasilkan nol', function () {
    expect(PenggajianCalculator::hitungNominal('persentase_pengali', '2', '5000000'))->toBe('0.00');
});

test('per hari dikalikan dari nilai per hari dan jumlah hari pada rentang tanggal', function () {
    expect(PenggajianCalculator::hitungNominal('per_hari', '15000', null, 20))->toBe('300000.00');
});

test('per hari dengan jumlah hari null dianggap nol', function () {
    expect(PenggajianCalculator::hitungNominal('per_hari', '15000', null, null))->toBe('0.00');
});

test('per hari dengan nol hari menghasilkan nol', function () {
    expect(PenggajianCalculator::hitungNominal('per_hari', '15000', null, 0))->toBe('0.00');
});

test('nominal input transaksi dan item list dipakai apa adanya', function (string $metode) {
    expect(PenggajianCalculator::hitungNominal($metode, '75000', null))->toBe('75000.00');
})->with(['nominal_tidak_tetap', 'nominal_tetap_list']);

test('harian manual dikalikan dari nilai per hari dan jumlah hari yang diketik manual', function () {
    expect(PenggajianCalculator::hitungNominal('harian_manual', '20000', null, 5))->toBe('100000.00');
});

test('harian manual dengan jumlah hari null dianggap nol', function () {
    expect(PenggajianCalculator::hitungNominal('harian_manual', '20000', null, null))->toBe('0.00');
});
