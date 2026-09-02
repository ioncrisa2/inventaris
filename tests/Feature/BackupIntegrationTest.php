<?php

use App\Services\LocalBackupService;
use Illuminate\Support\Str;

test('restic membuat snapshot database dan storage lalu lulus check serta restore', function () {
    if (! filter_var(env('BACKUP_INTEGRATION', false), FILTER_VALIDATE_BOOL)) {
        $this->markTestSkipped('Aktifkan BACKUP_INTEGRATION untuk smoke test Restic nyata.');
    }

    $root = sys_get_temp_dir().'/restic-integration-'.Str::random(16);
    config()->set('backup.repository', $root.'/repository');
    config()->set('backup.password', 'integration-password-not-for-production');
    config()->set('backup.password_file', null);
    config()->set('backup.metadata_path', $root.'/backup.json');
    config()->set('backup.storage_path', storage_path('app'));

    $service = app(LocalBackupService::class);
    $metadata = $service->backup();

    expect($metadata['status'])->toBe('success')
        ->and($metadata['snapshot_id'])->toBeString()
        ->and($service->check())->toBeTrue()
        ->and($service->restoreTest())->toBeTrue();

    $health = json_decode((string) file_get_contents($root.'/backup.json'), true, 8, JSON_THROW_ON_ERROR);
    expect($health)->toMatchArray([
        'status' => 'success',
        'restic_check_status' => 'success',
        'restore_test_status' => 'success',
    ]);
});
