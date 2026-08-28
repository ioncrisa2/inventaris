<?php

use App\Support\OwnerMetricFormatter;

test('rupiah formatter preserves large decimal strings without float precision loss', function () {
    expect(OwnerMetricFormatter::rupiah('999999999999999.49'))->toBe('Rp 999.999.999.999.999')
        ->and(OwnerMetricFormatter::rupiah('999999999999999.50'))->toBe('Rp 1.000.000.000.000.000')
        ->and(OwnerMetricFormatter::rupiah('0.00'))->toBe('Rp 0');
});

test('byte formatter presents capacity units', function () {
    expect(OwnerMetricFormatter::bytes(0))->toBe('0 B')
        ->and(OwnerMetricFormatter::bytes(1536))->toBe('1,5 KB')
        ->and(OwnerMetricFormatter::bytes(null))->toBe('Tidak tersedia');
});
