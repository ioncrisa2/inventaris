<?php

use App\Models\HariLibur;
use App\Services\HariOperasionalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

test('hari operasional default senin sampai sabtu ketika belum pernah disimpan', function () {
    expect(app(HariOperasionalService::class)->hariOperasional())->toBe([1, 2, 3, 4, 5, 6]);
});

test('simpan lalu baca kembali hari operasional', function () {
    $service = app(HariOperasionalService::class);

    $service->simpan([1, 2, 3, 4, 5]);

    expect($service->hariOperasional())->toBe([1, 2, 3, 4, 5]);
});

test('simpan mengurutkan dan membuang duplikat serta nilai di luar 1-7', function () {
    $service = app(HariOperasionalService::class);

    $service->simpan(['5', 3, 3, 1, '99', 0]);

    expect($service->hariOperasional())->toBe([1, 3, 5]);
});

test('is operasional true untuk hari kerja dan false untuk minggu secara default', function () {
    $service = app(HariOperasionalService::class);

    expect($service->isOperasional(Carbon::parse('2026-07-13')))->toBeTrue(); // Senin
    expect($service->isOperasional(Carbon::parse('2026-07-19')))->toBeFalse(); // Minggu
});

test('jumlah hari operasional mengecualikan hari minggu dalam rentang', function () {
    $service = app(HariOperasionalService::class);

    // 2026-07-01 (Rabu) s.d. 2026-07-20 (Senin): 20 hari kalender, 3 hari Minggu (5, 12, 19).
    $jumlah = $service->jumlahHariOperasional(Carbon::parse('2026-07-01'), Carbon::parse('2026-07-20'));

    expect($jumlah)->toBe(17);
});

test('jumlah hari operasional nol jika rentang hanya berisi hari minggu', function () {
    $service = app(HariOperasionalService::class);

    $jumlah = $service->jumlahHariOperasional(Carbon::parse('2026-07-19'), Carbon::parse('2026-07-19'));

    expect($jumlah)->toBe(0);
});

test('is libur pada true untuk tanggal libur nasional walau hari kerja biasa', function () {
    HariLibur::create(['tanggal' => '2026-07-15', 'keterangan' => 'Cuti Bersama']); // Rabu

    $service = app(HariOperasionalService::class);

    expect($service->isLiburPada(Carbon::parse('2026-07-15')))->toBeTrue();
    expect($service->isLiburPada(Carbon::parse('2026-07-13')))->toBeFalse(); // Senin biasa
});

test('libur dalam rentang menandai minggu (tanpa keterangan) dan libur nasional (dengan keterangan)', function () {
    HariLibur::create(['tanggal' => '2026-07-15', 'keterangan' => 'Cuti Bersama']); // Rabu

    $service = app(HariOperasionalService::class);

    $libur = $service->liburDalamRentang(Carbon::parse('2026-07-13'), Carbon::parse('2026-07-19'));

    expect($libur->get('2026-07-15'))->toBe('Cuti Bersama');
    expect($libur->get('2026-07-19'))->toBeNull(); // Minggu, libur mingguan biasa
    expect($libur->has('2026-07-13'))->toBeFalse(); // Senin biasa, bukan libur
});

test('jumlah hari operasional ikut mengecualikan tanggal libur nasional pada hari kerja biasa', function () {
    HariLibur::create(['tanggal' => '2026-07-15', 'keterangan' => 'Cuti Bersama']); // Rabu, di luar Minggu

    $service = app(HariOperasionalService::class);

    // 2026-07-01 s.d. 2026-07-20 = 20 hari kalender, 3 hari Minggu (5,12,19)
    // + 1 libur nasional Rabu (15) yang bukan hari Minggu = 16 hari operasional.
    $jumlah = $service->jumlahHariOperasional(Carbon::parse('2026-07-01'), Carbon::parse('2026-07-20'));

    expect($jumlah)->toBe(16);
});
