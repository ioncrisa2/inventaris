<?php

use App\Models\Karyawan;
use App\Models\Koperasi;
use App\Models\Role;
use App\Models\UnitKerja;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('staff role can view karyawan but cannot delete karyawan', function () {
    $this->actingAs(staffUser());

    $unitKerja = UnitKerja::create(['nama_unit' => 'IT']);
    $karyawan = Karyawan::create([
        'nik' => 'EMP-001',
        'nama_lengkap' => 'Budi Santoso',
        'tanggal_lahir' => '1990-01-01',
        'unit_kerja_id' => $unitKerja->id,
        'jabatan' => 'Staf IT',
        'status_karyawan' => 'Tetap',
        'gaji_pokok' => 7000000,
    ]);

    $this->get(route('karyawan.index'))->assertOk();

    $this->delete(route('karyawan.destroy', $karyawan))->assertForbidden();

    $this->assertDatabaseHas('karyawan', ['id' => $karyawan->id]);
});

test('staff role can view komponen gaji but cannot create it', function () {
    $this->actingAs(staffUser());

    $this->get(route('komponen-gaji.index'))->assertOk();

    $this->post(route('komponen-gaji.store'), [
        'nama_komponen' => 'Tunjangan Baru',
        'jenis' => 'Tunjangan',
        'metode_perhitungan' => 'nominal_tetap',
        'nilai_default' => 100000,
    ])->assertForbidden();

    $this->assertDatabaseMissing('komponen_gaji', ['nama_komponen' => 'Tunjangan Baru']);
});

test('staff role cannot access pengguna or role management pages', function () {
    $this->actingAs(staffUser());

    $this->get(route('pengguna.index'))->assertForbidden();
    $this->get(route('role.index'))->assertForbidden();
});

test('admin role can access pengguna and role management pages', function () {
    $this->actingAs(adminUser());

    $this->get(route('pengguna.index'))->assertOk();
    $this->get(route('pengguna.create'))->assertOk();
    $this->get(route('role.index'))->assertOk();
    $this->get(route('role.create'))->assertOk();
});

test('sidebar administration menu is hidden for staff and visible for admin', function () {
    $this->actingAs(staffUser());
    $this->get(route('dashboard'))->assertDontSee('Manajemen Pengguna');

    $this->actingAs(adminUser());
    $this->get(route('dashboard'))->assertSee('Manajemen Pengguna');
});

test('admin can create a role with a chosen set of permissions', function () {
    $this->actingAs(adminUser());
    $koperasi = Koperasi::create(['nama' => 'Koperasi Gudang']);

    $this->post(route('role.store'), [
        'name' => 'Kepala Gudang',
        'koperasi_id' => $koperasi->id,
        'permissions' => ['barang.view', 'barang.update'],
    ])->assertRedirect(route('role.index'));

    $role = Role::where('koperasi_id', $koperasi->id)->where('name', 'Kepala Gudang')->firstOrFail();

    expect($role->permissions->pluck('name')->all())
        ->toEqualCanonicalizing(['barang.view', 'barang.update']);
});

test('user cannot delete their own account', function () {
    $admin = adminUser();
    $this->actingAs($admin);

    $this->delete(route('pengguna.destroy', $admin))
        ->assertRedirect();

    $this->assertDatabaseHas('users', ['id' => $admin->id]);
});

test('admin can edit another user and change their role', function () {
    $this->actingAs(adminUser());

    $staff = staffUser(['name' => 'Staff Lama']);

    $this->get(route('pengguna.edit', $staff))->assertOk();

    $this->put(route('pengguna.update', $staff), [
        'name' => 'Staff Baru',
        'email' => $staff->email,
        'role' => 'super_admin',
    ])->assertRedirect(route('pengguna.index'));

    expect($staff->fresh()->name)->toBe('Staff Baru');
    expect($staff->fresh()->hasRole('super_admin'))->toBeTrue();
});

test('role still assigned to a user cannot be deleted', function () {
    $this->actingAs(adminUser());

    staffUser();

    $staffRole = Role::findByName('Staff', 'web');

    $this->delete(route('role.destroy', $staffRole))->assertRedirect();

    $this->assertDatabaseHas('roles', ['id' => $staffRole->id]);
});
