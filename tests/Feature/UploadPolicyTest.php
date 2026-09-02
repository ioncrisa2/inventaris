<?php

use App\Support\UploadPolicy;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;

test('seluruh policy upload memiliki accept eksplisit tanpa wildcard', function () {
    foreach (array_keys(config('uploads.policies')) as $name) {
        $accept = UploadPolicy::accept($name);

        expect($accept)->not->toContain('*')
            ->and($accept)->toStartWith('.');
    }
});

test('policy menolak ekstensi yang tidak diizinkan dan mime yang tidak cocok', function () {
    $unsupported = Validator::make(
        ['file' => UploadedFile::fake()->create('foto.heic', 10, 'image/heic')],
        ['file' => UploadPolicy::fileRules('employee_photo', true)],
    );
    $spoofed = Validator::make(
        ['file' => UploadedFile::fake()->create('dokumen.pdf', 10, 'text/plain')],
        ['file' => UploadPolicy::fileRules('business_documents', true)],
    );

    expect($unsupported->fails())->toBeTrue()
        ->and($unsupported->errors()->first('file'))->toContain('tidak didukung')
        ->and($spoofed->fails())->toBeTrue()
        ->and($spoofed->errors()->first('file'))->toContain('tidak sesuai');
});

test('policy menerapkan ukuran jumlah dan total ukuran file', function () {
    $tooLarge = Validator::make(
        ['file' => UploadedFile::fake()->create('besar.txt', 10 * 1024 + 1, 'text/plain')],
        ['file' => UploadPolicy::fileRules('product_attachments', true)],
    );

    $tooMany = Validator::make(
        ['files' => collect(range(1, 4))->map(
            fn (int $index) => UploadedFile::fake()->create("{$index}.txt", 1, 'text/plain'),
        )->all()],
        [
            'files' => UploadPolicy::collectionRules('product_attachments'),
            'files.*' => UploadPolicy::fileRules('product_attachments', true),
        ],
    );

    $tooLargeTogether = Validator::make(
        ['files' => [
            UploadedFile::fake()->create('satu.txt', 10 * 1024, 'text/plain'),
            UploadedFile::fake()->create('dua.txt', 10 * 1024, 'text/plain'),
            UploadedFile::fake()->create('tiga.txt', 1, 'text/plain'),
        ]],
        [
            'files' => UploadPolicy::collectionRules('product_attachments'),
            'files.*' => UploadPolicy::fileRules('product_attachments', true),
        ],
    );

    expect($tooLarge->fails())->toBeTrue()
        ->and($tooMany->fails())->toBeTrue()
        ->and($tooLargeTogether->fails())->toBeTrue()
        ->and($tooLargeTogether->errors()->first('files'))->toContain('Total ukuran');
});

test('policy menolak gambar yang melewati batas dimensi', function () {
    $validator = Validator::make(
        ['file' => UploadedFile::fake()->image('terlalu-lebar.jpg', 8001, 1)],
        ['file' => UploadPolicy::fileRules('asset_photo', true)],
    );

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->first('file'))->toContain('Dimensi gambar maksimal');
});

test('konfigurasi policy sesuai batas yang disepakati', function () {
    $employee = UploadPolicy::get('employee_photo');
    $gallery = UploadPolicy::get('asset_gallery');
    $documents = UploadPolicy::get('business_documents');
    $calendar = UploadPolicy::get('calendar_import');

    expect($employee['max_files'])->toBe(1)
        ->and($employee['max_file_kilobytes'])->toBe(15 * 1024)
        ->and($employee['camera'])->toBeTrue()
        ->and($gallery['max_files'])->toBe(5)
        ->and($gallery['max_total_kilobytes'])->toBe(50 * 1024)
        ->and($documents['max_file_kilobytes'])->toBe(20 * 1024)
        ->and($calendar['max_file_kilobytes'])->toBe(5 * 1024);
});
