<?php

use App\Support\OwnerAnalyticsPrivacy;

it('menghitung nilai uang dan persentase tanpa float', function () {
    $privacy = new OwnerAnalyticsPrivacy;

    expect($privacy->money('1234.5'))->toBe('1234.50')
        ->and($privacy->addMoney(['0.10', '0.20', '999.99']))->toBe('1000.29')
        ->and($privacy->percentage(2, 3))->toBe('66.7')
        ->and($privacy->averageMoney('10.00', 3))->toBe('3.33');
});

it('menyembunyikan nilai sensitif untuk cohort di bawah lima', function () {
    $privacy = new OwnerAnalyticsPrivacy;

    expect($privacy->sensitiveValue('2500000.00', 4))->toBe([
        'nilai' => null,
        'disembunyikan' => true,
        'pesan' => OwnerAnalyticsPrivacy::SUPPRESSION_MESSAGE,
    ])->and($privacy->sensitiveValue('2500000.00', 5))->toBe([
        'nilai' => '2500000.00',
        'disembunyikan' => false,
        'pesan' => null,
    ]);
});

it('menggabungkan label kelompok kecil agar tidak menjadi petunjuk identitas', function () {
    $privacy = new OwnerAnalyticsPrivacy;

    $result = $privacy->suppressDistribution([
        'Unit sangat kecil' => 1,
        'Unit lain yang kecil' => 4,
        'Unit aman' => 5,
    ]);

    expect($result)->toBe([
        [
            'label' => 'Unit aman',
            'total' => 5,
            'disembunyikan' => false,
            'pesan' => null,
        ],
        [
            'label' => 'Kelompok kecil',
            'total' => null,
            'disembunyikan' => true,
            'pesan' => OwnerAnalyticsPrivacy::SUPPRESSION_MESSAGE,
        ],
    ])->and(json_encode($result))->not->toContain('Unit sangat kecil')
        ->not->toContain('Unit lain yang kecil');
});
