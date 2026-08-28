<?php

use App\Console\Commands\ProvisionSystemOwner;
use App\Models\Koperasi;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

test('provision command creates a global owner with only the owner role', function () {
    $this->artisan('system-owner:provision', [
        '--name' => 'Owner Utama',
        '--email' => 'OWNER@example.com',
    ])
        ->expectsQuestion('Password baru', 'Password-Owner-123')
        ->expectsQuestion('Konfirmasi password baru', 'Password-Owner-123')
        ->expectsOutput('Akun system owner berhasil dibuat: owner@example.com')
        ->assertSuccessful();

    $owner = User::where('email', 'owner@example.com')->firstOrFail();

    expect($owner->name)->toBe('Owner Utama')
        ->and($owner->koperasi_id)->toBeNull()
        ->and($owner->unit_kerja_id)->toBeNull()
        ->and($owner->isSystemOwner())->toBeTrue()
        ->and($owner->roles)->toHaveCount(1)
        ->and($owner->getAllPermissions())->toHaveCount(0)
        ->and(Hash::check('Password-Owner-123', $owner->password))->toBeTrue()
        ->and(Role::query()
            ->where('name', 'system_owner')
            ->where('guard_name', 'web')
            ->whereNull('koperasi_id')
            ->count())->toBe(1);
});

test('provision command confirms and safely converts an existing global account', function () {
    $globalUser = superAdminUser([
        'name' => 'Global Lama',
        'email' => 'global@example.com',
    ]);
    $globalUser->givePermissionTo('barang.create');

    $this->artisan('system-owner:provision', [
        '--name' => 'Owner Pengganti',
        '--email' => 'global@example.com',
    ])
        ->expectsConfirmation(
            'Akun global dengan email global@example.com sudah ada. Ganti identitas, role, dan password akun ini?',
            'yes',
        )
        ->expectsQuestion('Password baru', 'Password-Baru-123')
        ->expectsQuestion('Konfirmasi password baru', 'Password-Baru-123')
        ->expectsOutput('Akun system owner berhasil diperbarui: global@example.com')
        ->assertSuccessful();

    $globalUser->refresh()->load('roles', 'permissions');

    expect($globalUser->name)->toBe('Owner Pengganti')
        ->and($globalUser->isSystemOwner())->toBeTrue()
        ->and($globalUser->isSuperAdmin())->toBeFalse()
        ->and($globalUser->roles)->toHaveCount(1)
        ->and($globalUser->permissions)->toHaveCount(0)
        ->and(Hash::check('Password-Baru-123', $globalUser->password))->toBeTrue();
});

test('provision command cancellation leaves an existing global account unchanged', function () {
    $superAdmin = superAdminUser([
        'name' => 'Super Tetap',
        'email' => 'stay@example.com',
        'password' => 'Password-Lama-123',
    ]);
    $password = $superAdmin->password;

    $this->artisan('system-owner:provision', [
        '--name' => 'Tidak Boleh Berubah',
        '--email' => 'stay@example.com',
    ])
        ->expectsConfirmation(
            'Akun global dengan email stay@example.com sudah ada. Ganti identitas, role, dan password akun ini?',
            'no',
        )
        ->expectsOutput('Provisioning system owner dibatalkan; tidak ada data yang diubah.')
        ->assertSuccessful();

    $superAdmin->refresh();

    expect($superAdmin->name)->toBe('Super Tetap')
        ->and($superAdmin->password)->toBe($password)
        ->and($superAdmin->isSuperAdmin())->toBeTrue()
        ->and($superAdmin->isSystemOwner())->toBeFalse();
});

test('provision command refuses to take over a tenant account', function () {
    $koperasi = Koperasi::create(['nama' => 'Koperasi Aman']);
    $tenant = adminPrimerUser($koperasi, [
        'name' => 'Admin Tenant',
        'email' => 'tenant@example.com',
    ]);
    $password = $tenant->password;

    $this->artisan('system-owner:provision', [
        '--name' => 'Percobaan Owner',
        '--email' => 'tenant@example.com',
    ])
        ->expectsOutput('Provisioning ditolak: email tersebut sudah dipakai oleh akun tenant.')
        ->assertFailed();

    $tenant->refresh();

    expect($tenant->name)->toBe('Admin Tenant')
        ->and($tenant->password)->toBe($password)
        ->and($tenant->koperasi_id)->toBe($koperasi->id)
        ->and($tenant->isAdminPrimer())->toBeTrue();
});

test('provision command refuses ambiguous duplicate global owner roles', function () {
    $this->seed(PermissionSeeder::class);

    $duplicate = new Role(['name' => 'system_owner', 'guard_name' => 'web']);
    $duplicate->koperasi_id = null;
    $duplicate->save();

    $this->artisan('system-owner:provision', [
        '--name' => 'Owner Ditolak',
        '--email' => 'duplicate@example.com',
    ])
        ->expectsOutput('Provisioning dihentikan: ditemukan lebih dari satu role global system_owner untuk guard web.')
        ->assertFailed();

    $this->assertDatabaseMissing('users', ['email' => 'duplicate@example.com']);
});

test('permission seeder keeps exactly one owner role without permissions', function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(PermissionSeeder::class);

    $roles = Role::query()
        ->where('name', 'system_owner')
        ->where('guard_name', 'web')
        ->whereNull('koperasi_id')
        ->get();

    expect($roles)->toHaveCount(1)
        ->and($roles->first()->permissions)->toHaveCount(0);
});

test('provision command never accepts a password option', function () {
    $command = Artisan::all()['system-owner:provision'];

    expect($command)->toBeInstanceOf(ProvisionSystemOwner::class)
        ->and($command->getDefinition()->hasOption('password'))->toBeFalse();
});
