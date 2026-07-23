<?php

use App\Services\TransactionalFileStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('deterministic file writes are reverted when their database transaction rolls back', function () {
    Storage::fake('public');
    Storage::disk('public')->put('demo/existing.txt', 'old contents');
    $files = app(TransactionalFileStorage::class);

    expect(fn () => DB::transaction(function () use ($files) {
        $files->put('public', 'demo/new.txt', 'new contents');
        $files->put('public', 'demo/existing.txt', 'overwritten contents');

        throw new RuntimeException('Force rollback.');
    }))->toThrow(RuntimeException::class);

    Storage::disk('public')->assertMissing('demo/new.txt');
    expect(Storage::disk('public')->get('demo/existing.txt'))->toBe('old contents');
});
