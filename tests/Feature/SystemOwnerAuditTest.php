<?php

use App\Models\Koperasi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

beforeEach(function () {
    Route::middleware(['web', 'auth', 'system_owner'])
        ->post('/_test/owner-audit', fn () => response('ok', 202))
        ->name('owner.audit.test');
});

test('owner access audit stores only safe request context', function () {
    $owner = systemOwnerUser();
    $koperasi = Koperasi::create(['nama' => 'Koperasi Filter Audit']);

    $this->actingAs($owner)
        ->withHeader('User-Agent', 'Owner Audit Test')
        ->post('/_test/owner-audit?'.http_build_query([
            'koperasi_id' => $koperasi->id,
            'tanggal_awal' => '2026-01-01',
            'tanggal_selesai' => '2026-08-28',
            'password' => 'tidak-boleh-tersimpan',
        ]))
        ->assertStatus(202);

    $this->assertDatabaseHas('activity_logs', [
        'actor_user_id' => $owner->id,
        'koperasi_id' => $koperasi->id,
        'action' => 'owner.audit.test',
        'route' => 'POST /_test/owner-audit',
        'response_status' => 202,
        'user_agent' => 'Owner Audit Test',
    ]);

    $filters = json_decode(
        (string) DB::table('activity_logs')->value('filters'),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    expect($filters)->toBe([
        'koperasi_id' => (string) $koperasi->id,
        'tanggal_awal' => '2026-01-01',
        'tanggal_selesai' => '2026-08-28',
    ])->not->toHaveKey('password');
});

test('important forbidden actions by another authenticated user are audited', function () {
    $user = superAdminUser();

    $this->actingAs($user)
        ->post('/_test/owner-audit')
        ->assertForbidden();

    $this->assertDatabaseHas('activity_logs', [
        'actor_user_id' => $user->id,
        'action' => 'owner.audit.test',
        'response_status' => 403,
    ]);
});

test('tenant mutations are audited but ordinary page views are not', function () {
    $admin = adminPrimerUser();

    $this->actingAs($admin)
        ->get(route('pengaturan.edit'))
        ->assertOk();

    expect(DB::table('activity_logs')->count())->toBe(0);

    $this->put(route('pengaturan.hari-operasional.update'), [
        'hari_operasional' => [1, 2, 3, 4, 5],
    ])->assertRedirect();

    $this->assertDatabaseHas('activity_logs', [
        'actor_user_id' => $admin->id,
        'koperasi_id' => $admin->koperasi_id,
        'action' => 'pengaturan.hari-operasional.update',
        'response_status' => 302,
    ]);
});

test('system owner can view and filter the activity log while other users cannot', function () {
    $owner = systemOwnerUser();
    $koperasi = Koperasi::create(['nama' => 'Koperasi Activity Log']);

    DB::table('activity_logs')->insert([
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
