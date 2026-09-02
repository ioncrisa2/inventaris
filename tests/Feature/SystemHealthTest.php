<?php

use App\Contracts\VirusScanner;
use App\Services\SystemHealthService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->artisan('migrate:fresh', ['--force' => true])->assertSuccessful();
    Cache::flush();
});

test('system health mengembalikan hasil terstruktur tanpa data sensitif', function () {
    Cache::put(config('owner_observability.scheduler.cache_key'), [
        'schema_version' => 1,
        'recorded_at' => now()->timestamp,
    ], 300);

    $health = app(SystemHealthService::class)->snapshot(true);

    expect($health)
        ->toHaveKeys(['schema_version', 'overall_status', 'checked_at', 'status_counts', 'checks'])
        ->and($health['checks'])->toHaveKeys([
            'application',
            'database',
            'cache',
            'queue',
            'antivirus',
            'media_pipeline',
            'scheduler',
            'backup',
            'storage',
            'mail',
            'application_errors',
        ])
        ->and($health['checks']['database']['metrics']['latency_ms'])->toBeNumeric()
        ->and($health['checks']['scheduler']['status'])->toBe('healthy');

    foreach ($health['checks'] as $check) {
        expect($check)
            ->toHaveKeys(['label', 'status', 'message', 'metrics', 'duration_ms', 'checked_at'])
            ->and($check['status'])->toBeIn(['healthy', 'warning', 'critical', 'unknown']);
    }

    $serialized = json_encode($health, JSON_THROW_ON_ERROR);

    expect($serialized)
        ->not->toContain((string) config('app.key'))
        ->not->toContain(base_path())
        ->not->toContain('payload')
        ->not->toContain('exception');
});

test('health media melaporkan clamav dan scan tertahan tanpa membuka isi file', function () {
    config()->set('uploads.features.scan_required', true);
    $scanner = Mockery::mock(VirusScanner::class);
    $scanner->shouldReceive('ping')->once()->andReturn(true);
    app()->instance(VirusScanner::class, $scanner);

    $health = app(SystemHealthService::class)->snapshot(true);

    expect($health['checks']['antivirus']['status'])->toBe('healthy')
        ->and($health['checks']['antivirus']['metrics']['reachable'])->toBeTrue()
        ->and($health['checks']['media_pipeline']['metrics'])->toHaveKeys([
            'pending_scan_files',
            'oldest_pending_scan_age_seconds',
            'failed_media_files',
            'failed_media_jobs',
            'staging_backlog',
            'orphan_files',
            'missing_files',
        ]);
});

test('kegagalan satu pemeriksaan tidak menjatuhkan seluruh health snapshot', function () {
    config()->set('cache.default', 'store-yang-tidak-ada');

    $health = app(SystemHealthService::class)->snapshot(true);

    expect($health['checks']['application']['status'])->toBe('healthy')
        ->and($health['checks']['database']['status'])->toBe('healthy')
        ->and($health['checks']['cache']['status'])->toBeIn(['warning', 'critical'])
        ->and($health['checks']['scheduler']['status'])->toBeIn(['warning', 'unknown']);
});

test('command heartbeat membuat scheduler check sehat', function () {
    $this->artisan('system:heartbeat')
        ->expectsOutputToContain('Scheduler heartbeat recorded.')
        ->assertSuccessful();

    $heartbeat = Cache::get(config('owner_observability.scheduler.cache_key'));
    expect($heartbeat)
        ->toBeArray()
        ->and($heartbeat)->toHaveKeys(['schema_version', 'recorded_at']);

    $health = app(SystemHealthService::class)->snapshot(true);
    expect($health['checks']['scheduler']['status'])->toBe('healthy');
});

test('metadata backup hanya mengeluarkan field terpilih dan tidak membocorkan payload mentah', function () {
    $path = tempnam(sys_get_temp_dir(), 'backup-health-');
    file_put_contents($path, json_encode([
        'status' => 'failed',
        'completed_at' => now()->toIso8601String(),
        'message' => 'password-database-super-rahasia',
        'remote_path' => '/server/private/tenant-backup.zip',
    ], JSON_THROW_ON_ERROR));
    config()->set('owner_observability.backup.metadata_path', $path);

    try {
        $health = app(SystemHealthService::class)->snapshot(true);
        $serialized = json_encode($health, JSON_THROW_ON_ERROR);

        expect($health['checks']['backup']['status'])->toBe('critical')
            ->and($serialized)->not->toContain('password-database-super-rahasia')
            ->and($serialized)->not->toContain('/server/private/tenant-backup.zip')
            ->and($serialized)->not->toContain($path);
    } finally {
        @unlink($path);
    }
});

test('statistik queue tidak pernah membawa payload job atau exception mentah', function () {
    config()->set('queue.default', 'database');
    DB::table('jobs')->insert([
        'queue' => 'default',
        'payload' => '{"token":"queue-secret-token"}',
        'attempts' => 0,
        'reserved_at' => null,
        'available_at' => now()->timestamp,
        'created_at' => now()->subMinute()->timestamp,
    ]);
    DB::table('failed_jobs')->insert([
        'uuid' => (string) Str::uuid(),
        'connection' => 'database',
        'queue' => 'default',
        'payload' => '{"password":"failed-job-password"}',
        'exception' => 'Stack trace dengan api-key-super-rahasia',
        'failed_at' => now(),
    ]);

    $health = app(SystemHealthService::class)->snapshot(true);
    $serialized = json_encode($health, JSON_THROW_ON_ERROR);

    expect($health['checks']['queue']['metrics']['pending_jobs'])->toBe(1)
        ->and($health['checks']['queue']['metrics']['failed_jobs'])->toBe(1)
        ->and($health['checks']['queue']['metrics']['oldest_pending_age_seconds'])->toBeGreaterThanOrEqual(60)
        ->and($serialized)->not->toContain('queue-secret-token')
        ->and($serialized)->not->toContain('failed-job-password')
        ->and($serialized)->not->toContain('api-key-super-rahasia');
});

test('health snapshot menggunakan cache singkat dengan penanda eksplisit', function () {
    $first = app(SystemHealthService::class)->snapshot(true);
    $second = app(SystemHealthService::class)->snapshot();

    expect($first['is_cached_snapshot'])->toBeFalse()
        ->and($second['is_cached_snapshot'])->toBeTrue()
        ->and($second['checked_at'])->toBe($first['checked_at']);
});
