<?php

use App\Models\Koperasi;
use App\Models\Role;
use App\Models\UnitKerja;
use App\Models\User;
use App\Services\UserService;
use App\Support\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

beforeEach(function () {
    Route::middleware(['web', 'auth', 'koperasi.active'])
        ->get('/_test/platform-account', fn () => 'platform-ok');

    Route::middleware(['web', 'auth', 'koperasi.active', 'system_owner'])
        ->get('/_test/system-owner-only', fn () => 'owner-ok');
});

test('system owner identity is global exact and does not inherit super admin permissions', function () {
    $owner = systemOwnerUser();

    expect($owner->isSystemOwner())->toBeTrue()
        ->and($owner->isPlatformAccount())->toBeTrue()
        ->and($owner->isSuperAdmin())->toBeFalse()
        ->and($owner->isTenantUser())->toBeFalse()
        ->and($owner->getAllPermissions())->toHaveCount(0)
        ->and($owner->roles)->toHaveCount(1)
        ->and($owner->roles->first()->isSystemOwnerRole())->toBeTrue()
        ->and($owner->roles->first()->displayName())->toBe('System Owner');
});

test('a tenant role named system owner cannot become a platform account', function () {
    $koperasi = Koperasi::create(['nama' => 'Koperasi Role Palsu']);
    $role = new Role(['name' => 'system_owner', 'guard_name' => 'web']);
    $role->koperasi_id = $koperasi->id;
    $role->save();

    $user = User::factory()->create();
    $user->koperasi_id = $koperasi->id;
    $user->save();
    $user->assignRole($role);

    expect($role->isSystemOwnerRole())->toBeFalse()
        ->and($user->fresh()->isSystemOwner())->toBeFalse()
        ->and($user->fresh()->isPlatformAccount())->toBeFalse()
        ->and($user->fresh()->isTenantUser())->toBeTrue();
});

test('system owner middleware only accepts a valid owner', function () {
    $this->get('/_test/system-owner-only')->assertRedirect(route('login'));

    $this->actingAs(superAdminUser())
        ->get('/_test/system-owner-only')
        ->assertForbidden();

    $this->actingAs(adminPrimerUser())
        ->get('/_test/system-owner-only')
        ->assertForbidden();

    $this->actingAs(systemOwnerUser())
        ->get('/_test/system-owner-only')
        ->assertOk()
        ->assertSee('owner-ok');
});

test('koperasi active middleware permits owner but still rejects a broken tenantless account', function () {
    $this->actingAs(systemOwnerUser())
        ->get('/_test/platform-account')
        ->assertOk();

    $brokenUser = User::factory()->create(['koperasi_id' => null]);

    $this->actingAs($brokenUser)
        ->get('/_test/platform-account')
        ->assertForbidden();
});

test('system owner does not bypass tenant scopes', function () {
    $koperasi = Koperasi::create(['nama' => 'Koperasi Data Terlindungi']);
    DB::table('unit_kerja')->insert([
        'koperasi_id' => $koperasi->id,
        'nama_unit' => 'Unit Rahasia',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs(systemOwnerUser());

    expect(UnitKerja::query()->count())->toBe(0)
        ->and(CurrentTenant::scopeQuery(UnitKerja::withoutGlobalScopes())->count())->toBe(0)
        ->and(CurrentTenant::id())->toBeNull();
});

test('ordinary user management hides and cannot mutate system owner accounts', function () {
    $owner = systemOwnerUser([
        'name' => 'Pemilik Platform',
        'email' => 'owner.hidden@example.com',
    ]);
    $superAdmin = superAdminUser();
    $ownerRole = $owner->roles()->firstOrFail();

    $this->actingAs($superAdmin)
        ->get(route('pengguna.index'))
        ->assertOk()
        ->assertDontSee('owner.hidden@example.com')
        ->assertDontSee('System Owner');

    $this->get(route('pengguna.create'))
        ->assertOk()
        ->assertDontSee('System Owner');

    $this->get(route('pengguna.edit', $owner))->assertForbidden();

    $this->from(route('pengguna.index'))
        ->delete(route('pengguna.bulk-destroy'), ['ids' => [$owner->id]])
        ->assertRedirect(route('pengguna.index'))
        ->assertSessionHas('error', 'Akun system owner tidak dapat dikelola melalui manajemen pengguna.');

    $this->post(route('pengguna.store'), [
        'name' => 'Owner Ilegal',
        'email' => 'owner.ilegal@example.com',
        'password' => 'Password-Aman-123',
        'role_id' => $ownerRole->id,
    ])->assertSessionHasErrors('role_id');

    expect(fn () => app(UserService::class)->destroy($superAdmin, $owner))
        ->toThrow(DomainException::class, 'Akun system owner tidak dapat dikelola melalui manajemen pengguna.');

    $this->assertDatabaseHas('users', ['id' => $owner->id]);
    $this->assertDatabaseMissing('users', ['email' => 'owner.ilegal@example.com']);
});

test('system owner can update their own profile outside user management', function () {
    $owner = systemOwnerUser([
        'name' => 'Owner Lama',
        'email' => 'owner.profile@example.com',
        'password' => 'password',
    ]);

    $this->actingAs($owner)
        ->put(route('profile.update'), [
            'name' => 'Owner Baru',
            'email' => 'owner.baru@example.com',
            'unit_kerja_id' => null,
            'current_password' => 'password',
        ])
        ->assertRedirect(route('profile.show'))
        ->assertSessionHas('profile_success');

    $owner->refresh();

    expect($owner->name)->toBe('Owner Baru')
        ->and($owner->email)->toBe('owner.baru@example.com')
        ->and($owner->koperasi_id)->toBeNull()
        ->and($owner->unit_kerja_id)->toBeNull()
        ->and($owner->isSystemOwner())->toBeTrue();
});
