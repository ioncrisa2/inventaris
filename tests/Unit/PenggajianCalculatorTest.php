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

test('per kehadiran dikalikan dari nilai per hari dan jumlah hadir', function () {
    expect(PenggajianCalculator::hitungNominal('per_kehadiran', '15000', null, 20))->toBe('300000.00');
});

test('per kehadiran dengan jumlah hadir null dianggap nol', function () {
    expect(PenggajianCalculator::hitungNominal('per_kehadiran', '15000', null, null))->toBe('0.00');
});

test('per kehadiran dengan nol hari hadir menghasilkan nol', function () {
    expect(PenggajianCalculator::hitungNominal('per_kehadiran', '15000', null, 0))->toBe('0.00');
});
