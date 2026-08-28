<?php

use App\Services\StorageUsageService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->artisan('migrate:fresh', ['--force' => true])->assertSuccessful();
    Cache::flush();
    Storage::fake('local');
    Storage::fake('public');
});

test('storage usage membedakan file aplikasi database dan volume host', function () {
    $ids = storageUsageFixture();

    Storage::disk('public')->put('barang-sampul/utama.jpg', str_repeat('a', 11));
    Storage::disk('public')->put('barang-foto/tambahan.jpg', str_repeat('b', 7));
    Storage::disk('local')->put('dokumen-barang/nota.pdf', str_repeat('c', 13));
    Storage::disk('public')->put('identitas/logo.png', str_repeat('d', 5));

    DB::table('barang')->where('id', $ids['barang'])->update(['foto_sampul' => 'barang-sampul/utama.jpg']);
    DB::table('foto_barang')->insert([
        'barang_id' => $ids['barang'],
        'path' => 'barang-foto/tambahan.jpg',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('dokumen_barang')->insert([
        'barang_id' => $ids['barang'],
        'jenis_dokumen' => 'nota',
        'nama_asli' => 'nota.pdf',
        'path' => 'dokumen-barang/nota.pdf',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('pengaturan')->insert([
        'koperasi_id' => $ids['koperasi'],
        'key' => 'identitas_logo_path',
        'value' => 'identitas/logo.png',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $snapshot = app(StorageUsageService::class)->snapshot(true);
    $categories = collect($snapshot['logical_application_files']['categories'])->keyBy('key');

    expect($snapshot)->toHaveKeys(['measured_at', 'logical_application_files', 'database', 'host_volume'])
        ->and($snapshot['logical_application_files']['scope'])->toBe('application_files')
        ->and($snapshot['host_volume']['scope'])->toBe('host_volume')
        ->and($snapshot['database']['scope'])->toBe('database_allocated_size')
        ->and($snapshot['database']['status'])->toBe('available')
        ->and($snapshot['database']['size_bytes'])->toBeGreaterThan(0)
        ->and($categories['item_photos']['bytes'])->toBe(18)
        ->and($categories['item_photos']['files_count'])->toBe(2)
        ->and($categories['item_documents']['bytes'])->toBe(13)
        ->and($categories['tenant_assets']['bytes'])->toBe(5)
        ->and($snapshot['logical_application_files']['total_bytes'])->toBe(36)
        ->and($snapshot['cooperative_usage']['scope'])->toBe('application_files_by_cooperative')
        ->and($snapshot['cooperative_usage']['rows'])->toHaveCount(1)
        ->and($snapshot['cooperative_usage']['rows'][0]['koperasi_id'])->toBe($ids['koperasi'])
        ->and($snapshot['cooperative_usage']['rows'][0]['bytes'])->toBe(36);

    $serialized = json_encode($snapshot, JSON_THROW_ON_ERROR);
    expect($serialized)
        ->not->toContain('barang-sampul/utama.jpg')
        ->not->toContain('nota.pdf')
        ->not->toContain(base_path());
});

test('path tidak aman dari database ditolak dan tidak pernah dikeluarkan', function () {
    $ids = storageUsageFixture();
    DB::table('barang')->where('id', $ids['barang'])->update(['foto_sampul' => '../../.env']);

    $snapshot = app(StorageUsageService::class)->snapshot(true);
    $category = collect($snapshot['logical_application_files']['categories'])->firstWhere('key', 'item_photos');

    expect($category['status'])->toBe('partial')
        ->and($category['bytes'])->toBe(0)
        ->and($category['unmeasured_files_count'])->toBe(1)
        ->and(json_encode($snapshot, JSON_THROW_ON_ERROR))->not->toContain('../../.env');
});

test('object storage tidak dipindai per file tanpa metadata ukuran', function () {
    $ids = storageUsageFixture();
    Storage::disk('public')->put('barang-sampul/remote.jpg', 'remote-content');
    DB::table('barang')->where('id', $ids['barang'])->update(['foto_sampul' => 'barang-sampul/remote.jpg']);
    config()->set('filesystems.disks.public.driver', 's3');

    $snapshot = app(StorageUsageService::class)->snapshot(true);
    $category = collect($snapshot['logical_application_files']['categories'])->firstWhere('key', 'item_photos');

    expect($category['status'])->toBe('partial')
        ->and($category['bytes'])->toBe(0)
        ->and($category['unmeasured_files_count'])->toBe(1);
});

test('kapasitas local driver aman dan tidak mengeluarkan root filesystem', function () {
    config()->set('filesystems.disks.capacity-test', [
        'driver' => 'local',
        'root' => sys_get_temp_dir(),
        'throw' => false,
    ]);

    $capacity = app(StorageUsageService::class)->capacitySnapshot('capacity-test');

    expect($capacity['status'])->toBe('available')
        ->and($capacity['scope'])->toBe('host_volume')
        ->and($capacity['total_bytes'])->toBeGreaterThan(0)
        ->and($capacity['available_bytes'])->toBeGreaterThanOrEqual(0)
        ->and($capacity)->not->toHaveKey('root')
        ->and(json_encode($capacity, JSON_THROW_ON_ERROR))->not->toContain(sys_get_temp_dir());
});

test('storage snapshot menggunakan cache dengan timestamp pengukuran yang sama', function () {
    $first = app(StorageUsageService::class)->snapshot(true);
    $second = app(StorageUsageService::class)->snapshot();

    expect($first['is_cached_snapshot'])->toBeFalse()
        ->and($second['is_cached_snapshot'])->toBeTrue()
        ->and($second['measured_at'])->toBe($first['measured_at']);
});

/**
 * @return array{koperasi: int, barang: int}
 */
function storageUsageFixture(): array
{
    $koperasi = DB::table('koperasi')->insertGetId([
        'nama' => 'Koperasi Storage',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $unit = DB::table('unit_kerja')->insertGetId([
        'koperasi_id' => $koperasi,
        'nama_unit' => 'Unit Storage',
        'kode' => 'STG',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $barang = DB::table('barang')->insertGetId([
        'koperasi_id' => $koperasi,
        'kode_barang' => 'STG-001',
        'nama_barang' => 'Barang Storage',
        'kategori' => 'Elektronik',
        'unit_kerja_id' => $unit,
        'lokasi_penempatan' => 'Kantor',
        'tanggal_perolehan' => '2026-08-01',
        'harga_perolehan' => 1000,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return compact('koperasi', 'barang');
}
