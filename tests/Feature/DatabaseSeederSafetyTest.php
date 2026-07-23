<?php

use App\Models\TransaksiGaji;
use App\Models\TransaksiGajiDetail;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');
});

test('payroll seed remains complete across a year boundary', function () {
    $this->travelTo('2027-01-21 12:00:00');
    $this->seed(DatabaseSeeder::class);

    $periode = TransaksiGaji::query()
        ->select(['tahun', 'bulan'])
        ->distinct()
        ->orderBy('tahun')
        ->orderBy('bulan')
        ->get()
        ->map(fn (TransaksiGaji $transaksi) => sprintf('%04d-%02d', $transaksi->tahun, $transaksi->bulan))
        ->all();

    expect($periode)->toBe(['2026-10', '2026-11', '2026-12'])
        ->and(TransaksiGajiDetail::where('metode_perhitungan_snapshot', 'per_kehadiran')->count())->toBe(39)
        ->and(TransaksiGajiDetail::where('metode_perhitungan_snapshot', 'per_kehadiran')->where('nominal_hasil', '<=', 0)->count())->toBe(0)
        ->and(TransaksiGajiDetail::whereHas('transaksiGaji', fn ($query) => $query->where('bulan', 12)->where('tahun', 2026))->count())->toBe(91)
        ->and(TransaksiGajiDetail::whereHas('transaksiGaji', fn ($query) => $query->where('bulan', 1)->where('tahun', 2027))->count())->toBe(0);
});

test('full demo seeder refuses to create predictable accounts outside local and testing', function () {
    $this->app->detectEnvironment(fn () => 'staging');

    expect(fn () => app(DatabaseSeeder::class)->run())->toThrow(RuntimeException::class);
    expect(User::count())->toBe(0);
});
