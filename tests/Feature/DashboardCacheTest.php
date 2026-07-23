<?php

use App\Models\Karyawan;
use App\Models\UnitKerja;
use App\Services\AbsensiService;
use App\Services\DashboardCache;
use App\Services\DashboardService;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    $this->artisan('migrate:fresh', ['--force' => true])->assertSuccessful();
});

test('dashboard memakai cache dan mutasi absensi mengganti generasi cache setelah commit', function () {
    Cache::flush();
    $admin = adminUser();
    $unit = UnitKerja::create(['nama_unit' => 'Teknologi', 'kode' => 'IT']);
    $karyawan = Karyawan::create([
        'nik' => 'EMP-CACHE',
        'nama_lengkap' => 'Karyawan Cache',
        'tanggal_lahir' => '1990-01-01',
        'unit_kerja_id' => $unit->id,
        'jabatan' => 'Staf',
        'status_karyawan' => 'Tetap',
        'gaji_pokok' => 5000000,
    ]);

    app(DashboardService::class)->widgets($admin, '2026-06-25');
    $generasiAwal = Cache::get('dashboard:generation');

    app(AbsensiService::class)->simpan($karyawan, [
        'tanggal' => '2026-07-01',
        'status' => 'Hadir',
        'catatan' => null,
    ]);

    expect($generasiAwal)->toBeString()
        ->and(Cache::get('dashboard:generation'))->not->toBe($generasiAwal);

    $hasil = app(DashboardService::class)->widgets($admin, '2026-06-25');
    expect($hasil['trenAbsensi']['ringkasan']['Hadir'])->toBe(1);
});

test('invalidasi cache tidak menghapus cache aplikasi lain', function () {
    Cache::forever('spatie.permission.cache.test-sentinel', 'tetap-ada');
    app(DashboardCache::class)->invalidate();

    expect(Cache::get('spatie.permission.cache.test-sentinel'))->toBe('tetap-ada');
});
