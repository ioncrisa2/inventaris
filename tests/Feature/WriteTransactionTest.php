<?php

use App\Models\Koperasi;
use App\Models\Pengaturan;
use App\Models\Role;
use App\Models\User;
use App\Services\KodeBarangGenerator;
use App\Services\RoleService;
use App\Services\UserService;
use Database\Seeders\DemoStaffRoleSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Spatie\Permission\Exceptions\RoleDoesNotExist;

uses(RefreshDatabase::class);

test('pembuatan pengguna dibatalkan jika sinkronisasi role gagal', function () {
    $actor = User::factory()->create();

    expect(fn () => app(UserService::class)->store($actor, [
        'name' => 'Pengguna Transaksi',
        'email' => 'transaction@example.com',
        'password' => 'password',
        'role' => 'Role Tidak Ada',
    ]))->toThrow(RoleDoesNotExist::class);

    $this->assertDatabaseMissing('users', ['email' => 'transaction@example.com']);
});

test('perubahan pengguna dibatalkan jika sinkronisasi role gagal', function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(DemoStaffRoleSeeder::class);
    $actor = User::factory()->create();
    $user = User::factory()->create(['name' => 'Nama Lama', 'email' => 'lama@example.com']);
    $user->assignRole('Staff');

    expect(fn () => app(UserService::class)->update($actor, $user, [
        'name' => 'Nama Baru',
        'email' => 'baru@example.com',
        'password' => '',
        'role' => 'Role Tidak Ada',
    ]))->toThrow(RoleDoesNotExist::class);

    expect($user->fresh()->name)->toBe('Nama Lama')
        ->and($user->fresh()->email)->toBe('lama@example.com')
        ->and($user->fresh()->hasRole('Staff'))->toBeTrue();
});

test('pembuatan dan perubahan role bersifat atomik', function () {
    $this->seed(PermissionSeeder::class);
    $actor = tap(User::factory()->create())->assignRole('super_admin');
    $koperasi = Koperasi::create(['nama' => 'Koperasi Atomik']);

    expect(fn () => app(RoleService::class)->store($actor, [
        'name' => 'Role Gagal',
        'koperasi_id' => $koperasi->id,
        'permissions' => ['permission.tidak-ada'],
    ]))->toThrow(PermissionDoesNotExist::class);
    $this->assertDatabaseMissing('roles', ['name' => 'Role Gagal']);

    $role = Role::create(['name' => 'Role Lama', 'guard_name' => 'web']);
    $role->syncPermissions(['dashboard.total-inventaris.view']);

    expect(fn () => app(RoleService::class)->update($role, [
        'name' => 'Role Baru',
        'permissions' => ['permission.tidak-ada'],
    ]))->toThrow(PermissionDoesNotExist::class);

    expect($role->fresh()->name)->toBe('Role Lama')
        ->and($role->fresh()->hasPermissionTo('dashboard.total-inventaris.view'))->toBeTrue();
});

test('dua pengaturan inventaris disimpan secara atomik', function () {
    Pengaturan::set('format_kode_barang', 'FORMAT-LAMA');
    Pengaturan::set('digit_nomor_urut', '4');

    Pengaturan::updating(function (Pengaturan $pengaturan) {
        if ($pengaturan->key === 'digit_nomor_urut') {
            throw new RuntimeException('Paksa rollback pengaturan kedua.');
        }
    });

    expect(fn () => app(KodeBarangGenerator::class)->simpanPengaturan('FORMAT-BARU', 6))
        ->toThrow(RuntimeException::class);

    expect(Pengaturan::get('format_kode_barang'))->toBe('FORMAT-LAMA')
        ->and(Pengaturan::get('digit_nomor_urut'))->toBe('4');
});
