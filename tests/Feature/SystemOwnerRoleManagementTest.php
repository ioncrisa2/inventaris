<?php

use App\Models\Koperasi;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('system owner has an audited role management control plane without operational access', function () {
    $owner = systemOwnerUser();

    $this->actingAs($owner)
        ->get(route('owner.roles.index'))
        ->assertOk()
        ->assertSeeText('Role & Hak Akses')
        ->assertDontSee('System Owner');

    $this->get(route('role.index'))->assertForbidden();

    expect(DB::table('activity_logs')->count())->toBe(0);
});

test('super admin role and user screens do not reveal the system owner identity', function () {
    $owner = systemOwnerUser([
        'name' => 'Pemilik Platform Tersembunyi',
        'email' => 'owner.secret@example.com',
    ]);
    $superAdmin = superAdminUser();

    $this->actingAs($superAdmin)
        ->get(route('role.index'))
        ->assertOk()
        ->assertDontSee('System Owner');

    $this->get(route('pengguna.index'))
        ->assertOk()
        ->assertDontSee('owner.secret@example.com')
        ->assertDontSee('Pemilik Platform Tersembunyi');
});

test('system owner can create a tenant role with selected permissions', function () {
    $owner = systemOwnerUser();
    $koperasi = Koperasi::create(['nama' => 'Koperasi Role Owner']);

    $this->actingAs($owner);

    $this->get(route('owner.roles.create'))
        ->assertOk()
        ->assertSeeText('Koperasi Role Owner');

    $this->post(route('owner.roles.store'), [
        'name' => 'Operator Penggajian',
        'koperasi_id' => $koperasi->id,
        'permissions' => ['transaksi-gaji.view', 'transaksi-gaji.publish'],
    ])
        ->assertRedirect(route('owner.roles.index'));

    $role = Role::query()
        ->where('koperasi_id', $koperasi->id)
        ->where('name', 'Operator Penggajian')
        ->firstOrFail();

    expect($role->permissions->pluck('name')->all())
        ->toEqualCanonicalizing(['transaksi-gaji.view', 'transaksi-gaji.publish']);

    $this->put(route('owner.roles.update', $role), [
        'name' => 'Supervisor Penggajian',
        'permissions' => ['transaksi-gaji.view'],
    ])->assertRedirect(route('owner.roles.index'));

    expect($role->fresh()->name)->toBe('Supervisor Penggajian')
        ->and($role->fresh()->permissions->pluck('name')->all())
        ->toBe(['transaksi-gaji.view']);

    $this->delete(route('owner.roles.destroy', $role))
        ->assertRedirect(route('owner.roles.index'));

    $this->assertDatabaseMissing('roles', ['id' => $role->id]);
});

test('system owner can update system role permissions but cannot rename or manage its own identity role', function () {
    $owner = systemOwnerUser();
    $koperasi = Koperasi::create(['nama' => 'Koperasi Permission Owner']);
    adminPrimerUser($koperasi);

    $adminPrimerRole = Role::query()
        ->where('name', 'admin_primer')
        ->where('koperasi_id', $koperasi->id)
        ->firstOrFail();

    $this->actingAs($owner);

    $this->get(route('owner.roles.edit', $adminPrimerRole))
        ->assertOk()
        ->assertSeeText('Atur Hak Akses Admin Primer');

    $this->put(route('owner.roles.update', $adminPrimerRole), [
        'name' => 'admin_primer',
        'permissions' => ['transaksi-gaji.view', 'transaksi-gaji.publish'],
    ])
        ->assertRedirect(route('owner.roles.index'));

    $adminPrimerRole->refresh();

    expect($adminPrimerRole->name)->toBe('admin_primer')
        ->and($adminPrimerRole->permissions->pluck('name')->all())
        ->toEqualCanonicalizing(['transaksi-gaji.view', 'transaksi-gaji.publish']);

    $ownerRole = $owner->roles()->firstOrFail();

    $this->get(route('owner.roles.edit', $ownerRole))->assertNotFound();
    $this->put(route('owner.roles.update', $ownerRole), [
        'name' => 'system_owner',
        'permissions' => ['role.view'],
    ])->assertForbidden();

    expect($ownerRole->fresh()->permissions)->toHaveCount(0);
});

test('non owner accounts cannot enter the owner role management routes', function () {
    $this->actingAs(superAdminUser())
        ->get(route('owner.roles.index'))
        ->assertForbidden();

    $this->actingAs(adminPrimerUser())
        ->get(route('owner.roles.index'))
        ->assertForbidden();

    expect(DB::table('activity_logs')->count())->toBe(0);
});
