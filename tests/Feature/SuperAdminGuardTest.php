<?php

use App\Models\Koperasi;
use App\Models\Role;
use App\Services\RoleService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('non super admin cannot create a role even via direct request', function () {
    $this->seed(PermissionSeeder::class);
    $staff = staffUser();
    $staff->givePermissionTo('role.create');

    $this->actingAs($staff)
        ->post(route('role.store'), [
            'name' => 'Role Siluman',
            'permissions' => ['barang.view'],
        ])->assertForbidden();

    $this->assertDatabaseMissing('roles', ['name' => 'Role Siluman']);
});

test('role service always binds an admin primer created role to the actor koperasi', function () {
    $koperasiA = Koperasi::create(['nama' => 'Koperasi Service A']);
    $koperasiB = Koperasi::create(['nama' => 'Koperasi Service B']);
    $adminPrimer = adminPrimerUser($koperasiA);

    $role = app(RoleService::class)->store($adminPrimer, [
        'name' => 'Operator Service',
        'koperasi_id' => $koperasiB->id,
        'permissions' => ['barang.view'],
    ]);

    expect($role->koperasi_id)->toBe($koperasiA->id)
        ->and($role->permissions->pluck('name')->all())->toBe(['barang.view']);
});

test('non super admin cannot delete a role even with role.delete permission', function () {
    $this->seed(PermissionSeeder::class);
    $superAdmin = superAdminUser();
    $koperasi = Koperasi::create(['nama' => 'Koperasi Guard']);
    $role = app(RoleService::class)->store($superAdmin, [
        'name' => 'Role Untuk Dihapus',
        'koperasi_id' => $koperasi->id,
        'permissions' => [],
    ]);

    $staff = staffUser();
    $staff->givePermissionTo('role.delete');

    $this->actingAs($staff)
        ->delete(route('role.destroy', $role))
        ->assertRedirect();

    $this->assertDatabaseHas('roles', ['id' => $role->id]);
});

test('non super admin cannot assign super_admin role to a user', function () {
    $this->seed(PermissionSeeder::class);
    $staff = staffUser();
    $staff->givePermissionTo(['pengguna.create', 'pengguna.update']);
    $target = staffUser(['koperasi_id' => $staff->koperasi_id]);
    $superAdminRole = Role::query()
        ->where('name', 'super_admin')
        ->whereNull('koperasi_id')
        ->firstOrFail();

    $this->actingAs($staff)
        ->put(route('pengguna.update', $target), [
            'name' => $target->name,
            'email' => $target->email,
            'role_id' => $superAdminRole->id,
        ])->assertRedirect();

    expect($target->fresh()->hasRole('super_admin'))->toBeFalse();
});

test('super admin can still create and delete roles normally', function () {
    $superAdmin = superAdminUser();
    $koperasi = Koperasi::create(['nama' => 'Koperasi Sah']);

    $this->actingAs($superAdmin)
        ->post(route('role.store'), [
            'name' => 'Role Sah',
            'koperasi_id' => $koperasi->id,
            'permissions' => ['barang.view'],
        ])->assertRedirect(route('role.index'));

    $this->assertDatabaseHas('roles', ['name' => 'Role Sah']);

    $role = Role::where('koperasi_id', $koperasi->id)->where('name', 'Role Sah')->firstOrFail();

    $this->actingAs($superAdmin)
        ->delete(route('role.destroy', $role))
        ->assertRedirect(route('role.index'));

    $this->assertDatabaseMissing('roles', ['id' => $role->id]);
});
