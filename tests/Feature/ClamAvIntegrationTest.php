<?php

use App\Contracts\VirusScanner;

test('clamav daemon memindai stream clean dan signature eicar', function () {
    if (! filter_var(env('CLAMAV_INTEGRATION', false), FILTER_VALIDATE_BOOL)) {
        $this->markTestSkipped('Aktifkan CLAMAV_INTEGRATION untuk smoke test daemon nyata.');
    }

    $scanner = app(VirusScanner::class);
    expect($scanner->ping())->toBeTrue();

    $clean = fopen('php://temp', 'w+b');
    fwrite($clean, 'dokumen koperasi yang aman');
    rewind($clean);
    try {
        expect($scanner->scan($clean))->toBe('clean');
    } finally {
        fclose($clean);
    }

    $eicar = fopen('php://temp', 'w+b');
    fwrite($eicar, 'X5O!P%@AP[4\\PZX54(P^)7CC)7}$EICAR-STANDARD-ANTIVIRUS-TEST-FILE!$H+H*');
    rewind($eicar);
    try {
        expect($scanner->scan($eicar))->toBe('infected');
    } finally {
        fclose($eicar);
    }
});
