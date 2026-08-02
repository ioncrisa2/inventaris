<?php

use App\Models\Koperasi;
use App\Models\Role;
use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('non super admin cannot access koperasi management pages', function () {
    $this->actingAs(staffUser());

    $this->get(route('koperasi.index'))->assertForbidden();
    $this->get(route('koperasi.create'))->assertForbidden();
});

test('super admin can view koperasi index', function () {
    $this->actingAs(superAdminUser());

    $this->get(route('koperasi.index'))->assertOk();
});

test('super admin provisioning a koperasi creates the koperasi, its admin_primer role, and the first user', function () {
    $this->actingAs(superAdminUser());

    $this->post(route('koperasi.store'), [
        'nama' => 'Koperasi Sejahtera',
        'admin_nama' => 'Budi Primer',
        'admin_email' => 'budi@koperasi-sejahtera.test',
        'admin_password' => 'password123',
    ])->assertRedirect(route('koperasi.index'));

    $koperasi = Koperasi::where('nama', 'Koperasi Sejahtera')->firstOrFail();
    $user = User::where('email', 'budi@koperasi-sejahtera.test')->firstOrFail();
    $role = Role::where('name', 'admin_primer')->where('koperasi_id', $koperasi->id)->firstOrFail();

    expect($user->koperasi_id)->toBe($koperasi->id)
        ->and($user->hasRole($role))->toBeTrue()
        ->and($role->permissions->pluck('name'))->not->toContain('role.create')
        ->and($role->permissions->pluck('name'))->not->toContain('role.delete')
        ->and($role->permissions->pluck('name'))->toContain('barang.view')
        ->and($role->permissions->pluck('name'))->toContain('pengguna.update');
});

test('two koperasi each get their own admin_primer role without name collisions', function () {
    $this->actingAs(superAdminUser());

    $this->post(route('koperasi.store'), [
        'nama' => 'Koperasi A',
        'admin_nama' => 'Admin A',
        'admin_email' => 'admin@koperasi-a.test',
        'admin_password' => 'password123',
    ]);
    $this->post(route('koperasi.store'), [
        'nama' => 'Koperasi B',
        'admin_nama' => 'Admin B',
        'admin_email' => 'admin@koperasi-b.test',
        'admin_password' => 'password123',
    ]);

    $koperasiA = Koperasi::where('nama', 'Koperasi A')->firstOrFail();
    $koperasiB = Koperasi::where('nama', 'Koperasi B')->firstOrFail();
    $userA = User::where('email', 'admin@koperasi-a.test')->firstOrFail();
    $userB = User::where('email', 'admin@koperasi-b.test')->firstOrFail();

    expect(Role::where('name', 'admin_primer')->count())->toBe(2)
        ->and($userA->koperasi_id)->toBe($koperasiA->id)
        ->and($userB->koperasi_id)->toBe($koperasiB->id);

    // Masing-masing admin_primer cuma benar-benar terhubung ke role milik koperasinya sendiri.
    $roleA = Role::where('name', 'admin_primer')->where('koperasi_id', $koperasiA->id)->firstOrFail();
    $roleB = Role::where('name', 'admin_primer')->where('koperasi_id', $koperasiB->id)->firstOrFail();

    expect($userA->hasRole($roleA))->toBeTrue()
        ->and($userA->hasRole($roleB))->toBeFalse()
        ->and($userB->hasRole($roleB))->toBeTrue()
        ->and($userB->hasRole($roleA))->toBeFalse();
});

test('admin_primer of one koperasi cannot see data belonging to another koperasi', function () {
    $this->actingAs(superAdminUser());

    $this->post(route('koperasi.store'), [
        'nama' => 'Koperasi A',
        'admin_nama' => 'Admin A',
        'admin_email' => 'admin@koperasi-a.test',
        'admin_password' => 'password123',
    ]);
    $this->post(route('koperasi.store'), [
        'nama' => 'Koperasi B',
        'admin_nama' => 'Admin B',
        'admin_email' => 'admin@koperasi-b.test',
        'admin_password' => 'password123',
    ]);

    $userA = User::where('email', 'admin@koperasi-a.test')->firstOrFail();
    $userB = User::where('email', 'admin@koperasi-b.test')->firstOrFail();

    $this->actingAs($userA);
    UnitKerja::create(['nama_unit' => 'Unit Rahasia A']);

    $this->actingAs($userB);
    expect(UnitKerja::count())->toBe(0);

    $this->actingAs($userA);
    expect(UnitKerja::count())->toBe(1);
});

test('super admin can update koperasi expiry and deactivate it', function () {
    $this->actingAs(superAdminUser());

    $this->post(route('koperasi.store'), [
        'nama' => 'Koperasi C',
        'admin_nama' => 'Admin C',
        'admin_email' => 'admin@koperasi-c.test',
        'admin_password' => 'password123',
    ]);
    $koperasi = Koperasi::where('nama', 'Koperasi C')->firstOrFail();
    expect($koperasi->is_active)->toBeTrue();

    $expiresAt = now()->addYear()->format('Y-m-d');

    $this->put(route('koperasi.update', $koperasi), [
        'nama' => 'Koperasi C',
        'expires_at' => $expiresAt,
        'is_active' => '1',
    ])->assertRedirect(route('koperasi.index'));

    expect($koperasi->fresh()->is_active)->toBeTrue()
        ->and($koperasi->fresh()->expires_at->format('Y-m-d'))->toBe($expiresAt);

    $this->put(route('koperasi.update', $koperasi), [
        'nama' => 'Koperasi C',
        'expires_at' => $expiresAt,
    ])->assertRedirect(route('koperasi.index'));

    expect($koperasi->fresh()->is_active)->toBeFalse();
});
