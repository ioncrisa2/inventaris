<?php

use App\Http\Middleware\AuditSystemOwnerAccess;
use App\Models\Koperasi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

beforeEach(function () {
    Route::middleware(['web', 'auth', 'system_owner', AuditSystemOwnerAccess::class])
        ->get('/_test/owner-audit', fn () => response('ok', 202))
        ->name('owner.audit.test');
});

test('owner access audit stores only safe request context', function () {
    $owner = systemOwnerUser();
    $koperasi = Koperasi::create(['nama' => 'Koperasi Filter Audit']);

    $this->actingAs($owner)
        ->withHeader('User-Agent', 'Owner Audit Test')
        ->get('/_test/owner-audit?'.http_build_query([
            'koperasi_id' => $koperasi->id,
            'tanggal_awal' => '2026-01-01',
            'tanggal_selesai' => '2026-08-28',
            'password' => 'tidak-boleh-tersimpan',
        ]))
        ->assertStatus(202);

    $this->assertDatabaseHas('system_owner_audit_logs', [
        'actor_user_id' => $owner->id,
        'koperasi_id' => $koperasi->id,
        'action' => 'owner.audit.test',
        'route' => 'GET /_test/owner-audit',
        'response_status' => 202,
        'user_agent' => 'Owner Audit Test',
    ]);

    $filters = json_decode(
        (string) DB::table('system_owner_audit_logs')->value('filters'),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    expect($filters)->toBe([
        'koperasi_id' => (string) $koperasi->id,
        'tanggal_awal' => '2026-01-01',
        'tanggal_selesai' => '2026-08-28',
    ])->not->toHaveKey('password');
});

test('non owner requests are never written to the owner audit log', function () {
    $this->actingAs(superAdminUser())
        ->get('/_test/owner-audit')
        ->assertForbidden();

    expect(DB::table('system_owner_audit_logs')->count())->toBe(0);
});

test('system owner can view and filter the activity log while other users cannot', function () {
    $owner = systemOwnerUser();
    $koperasi = Koperasi::create(['nama' => 'Koperasi Activity Log']);

    DB::table('system_owner_audit_logs')->insert([
        'actor_user_id' => $owner->id,
        'koperasi_id' => $koperasi->id,
        'action' => 'owner.features.update',
        'route' => 'PATCH /owner/features/inventaris',
        'response_status' => 200,
        'ip_address' => '127.0.0.1',
        'created_at' => now(),
    ]);

    $this->actingAs($owner)
        ->get(route('owner.activity-logs.index', [
            'koperasi_id' => $koperasi->id,
            'method' => 'PATCH',
            'response_status' => 200,
        ]))
        ->assertOk()
        ->assertSeeText('Activity Log')
        ->assertSeeText('owner.features.update')
        ->assertSeeText('Koperasi Activity Log');

    $this->actingAs(superAdminUser())
        ->get(route('owner.activity-logs.index'))
        ->assertForbidden();
});
