<?php

use App\Models\Barang;
use App\Models\Karyawan;
use App\Models\Koperasi;
use App\Models\Role;
use App\Models\UnitKerja;
use App\Models\User;
use App\Support\PermissionCatalog;
use App\Support\TenantRule;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

test('tenant role named super_admin cannot become a global super admin or bypass tenant scope', function () {
    $koperasiA = Koperasi::create(['nama' => 'Primer A']);
    $koperasiB = Koperasi::create(['nama' => 'Primer B']);
    $adminB = adminPrimerUser($koperasiB);

    $maliciousRole = new Role(['name' => 'super_admin', 'guard_name' => 'web']);
    $maliciousRole->koperasi_id = $koperasiA->id;
    $maliciousRole->save();
    $maliciousRole->syncPermissions(PermissionCatalog::all());

    $maliciousUser = User::factory()->create();
    $maliciousUser->koperasi_id = $koperasiA->id;
    $maliciousUser->save();
    $maliciousUser->assignRole($maliciousRole);

    $this->actingAs($adminB);
    $unitB = UnitKerja::create(['nama_unit' => 'Unit Rahasia B']);
    $karyawanB = Karyawan::create([
        'nik' => 'B-SECRET',
        'nama_lengkap' => 'Karyawan Rahasia B',
        'tanggal_lahir' => '1990-01-01',
        'unit_kerja_id' => $unitB->id,
        'jabatan' => 'Staf',
        'status_karyawan' => 'PKWTT',
        'gaji_pokok' => 5000000,
    ]);

    $this->actingAs($maliciousUser);

    expect($maliciousUser->fresh()->isSuperAdmin())->toBeFalse()
        ->and(Karyawan::count())->toBe(0);

    $this->get(route('karyawan.show', $karyawanB))->assertNotFound();
    $this->get(route('koperasi.index'))->assertForbidden();
});

test('non-super account without koperasi is denied and cannot read orphan tenant rows', function () {
    $this->seed(PermissionSeeder::class);

    $unitYatim = UnitKerja::create(['nama_unit' => 'Unit Yatim']);
    Karyawan::create([
        'nik' => 'ORPHAN-001',
        'nama_lengkap' => 'Karyawan Yatim',
        'tanggal_lahir' => '1990-01-01',
        'unit_kerja_id' => $unitYatim->id,
        'jabatan' => 'Staf',
        'status_karyawan' => 'PKWTT',
        'gaji_pokok' => 5000000,
    ]);

    $brokenUser = User::factory()->create(['koperasi_id' => null]);
    $brokenUser->givePermissionTo(['karyawan.view', 'unit-kerja.view']);
    $this->actingAs($brokenUser);

    expect(UnitKerja::count())->toBe(0)
        ->and(Karyawan::count())->toBe(0);

    $this->get(route('dashboard'))->assertForbidden();
    $this->get(route('karyawan.index'))->assertForbidden();
});

test('tenant admin cannot rename a custom role to a reserved system role', function () {
    $admin = adminPrimerUser();
    $role = new Role(['name' => 'Kasir', 'guard_name' => 'web']);
    $role->koperasi_id = $admin->koperasi_id;
    $role->save();

    $this->actingAs($admin)
        ->from(route('role.edit', $role))
        ->put(route('role.update', $role), [
            'name' => 'super_admin',
            'permissions' => ['barang.view'],
        ])
        ->assertRedirect(route('role.edit', $role))
        ->assertSessionHasErrors('name');

    expect($role->fresh()->name)->toBe('Kasir');
});

test('foreign tenant identifiers are rejected in writes bulk actions filters and profile updates', function () {
    $koperasiA = Koperasi::create(['nama' => 'Primer A']);
    $koperasiB = Koperasi::create(['nama' => 'Primer B']);
    $adminA = adminPrimerUser($koperasiA);
    $adminB = adminPrimerUser($koperasiB);

    $this->actingAs($adminB);
    $unitB = UnitKerja::create(['nama_unit' => 'Unit B', 'kode' => 'UB']);
    $barangB = Barang::create([
        'kode_barang' => 'B-001',
        'nama_barang' => 'Barang B',
        'kategori' => 'Bukan Bangunan - Kelompok 1',
        'jenis_barang' => 'Access point Wi-Fi',
        'unit_kerja_id' => $unitB->id,
        'tanggal_perolehan' => '2026-01-01',
        'harga_perolehan' => 1000000,
    ]);
    $roleB = new Role(['name' => 'Operator B', 'guard_name' => 'web']);
    $roleB->koperasi_id = $koperasiB->id;
    $roleB->save();

    $this->actingAs($adminA);

    $this->post(route('barang.store'), [
        'nama_barang' => 'Percobaan Silang',
        'kategori' => 'Bukan Bangunan - Kelompok 1',
        'jenis_barang' => 'Access point Wi-Fi',
        'unit_kerja_id' => $unitB->id,
        'lokasi_penempatan' => 'Kantor Pusat',
        'tanggal_perolehan' => '2026-01-01',
        'harga_perolehan' => 1000000,
    ])->assertSessionHasErrors('unit_kerja_id');

    $this->post(route('barang.barcode.bulk'), [
        'barang_ids' => [$barangB->id],
    ])->assertSessionHasErrors('barang_ids.0');

    $this->get(route('laporan.inventaris', [
        'unit_kerja_id' => $unitB->id,
    ]))->assertSessionHasErrors('unit_kerja_id');

    $this->put(route('profile.update'), [
        'name' => $adminA->name,
        'email' => $adminA->email,
        'unit_kerja_id' => $unitB->id,
    ])->assertSessionHasErrors('unit_kerja_id', null, 'updateProfile');

    $this->post(route('pengguna.store'), [
        'name' => 'User Silang',
        'email' => 'silang@example.com',
        'password' => 'Password123!',
        'unit_kerja_id' => $unitB->id,
        'role_id' => $roleB->id,
    ])->assertSessionHasErrors(['unit_kerja_id', 'role_id']);

    $this->assertDatabaseMissing('barang', ['nama_barang' => 'Percobaan Silang']);
    $this->assertDatabaseMissing('users', ['email' => 'silang@example.com']);
});

test('super admin is read-only for operational data but can manage the control plane', function () {
    $adminPrimer = adminPrimerUser();
    $this->actingAs($adminPrimer);
    UnitKerja::create(['nama_unit' => 'Unit Primer']);

    $superAdmin = superAdminUser();
    $this->actingAs($superAdmin);

    expect($superAdmin->can('unit-kerja.view'))->toBeTrue()
        ->and($superAdmin->can('unit-kerja.create'))->toBeFalse()
        ->and($superAdmin->can('pengguna.create'))->toBeTrue()
        ->and($superAdmin->can('role.create'))->toBeTrue();

    $this->get(route('unit-kerja.index'))->assertOk()->assertSee('Unit Primer');
    $this->post(route('unit-kerja.store'), [
        'nama_unit' => 'Unit Global Ilegal',
    ])->assertForbidden();
    $this->get(route('barang.create'))->assertForbidden();

    $this->assertDatabaseMissing('unit_kerja', ['nama_unit' => 'Unit Global Ilegal']);
});

test('employee identity uniqueness is scoped per koperasi and remains strict inside one koperasi', function () {
    $koperasiA = Koperasi::create(['nama' => 'Primer A']);
    $koperasiB = Koperasi::create(['nama' => 'Primer B']);
    $adminA = adminPrimerUser($koperasiA);
    $adminB = adminPrimerUser($koperasiB);

    $this->actingAs($adminA);
    $unitA = UnitKerja::create(['nama_unit' => 'Unit A']);
    Karyawan::create([
        'nik' => 'SAMA-001',
        'nomor_ktp' => '1601010101010101',
        'nama_lengkap' => 'Karyawan A',
        'tanggal_lahir' => '1990-01-01',
        'unit_kerja_id' => $unitA->id,
        'jabatan' => 'Staf',
        'status_karyawan' => 'PKWTT',
        'gaji_pokok' => 5000000,
    ]);

    expect(Validator::make(
        ['nik' => 'SAMA-001', 'nomor_ktp' => '1601010101010101'],
        [
            'nik' => [TenantRule::unique('karyawan', 'nik')],
            'nomor_ktp' => [TenantRule::unique('karyawan', 'nomor_ktp')],
        ],
    )->fails())->toBeTrue();

    $this->actingAs($adminB);
    $unitB = UnitKerja::create(['nama_unit' => 'Unit B']);

    expect(Validator::make(
        ['nik' => 'SAMA-001', 'nomor_ktp' => '1601010101010101'],
        [
            'nik' => [TenantRule::unique('karyawan', 'nik')],
            'nomor_ktp' => [TenantRule::unique('karyawan', 'nomor_ktp')],
        ],
    )->passes())->toBeTrue();

    Karyawan::create([
        'nik' => 'SAMA-001',
        'nomor_ktp' => '1601010101010101',
        'nama_lengkap' => 'Karyawan B',
        'tanggal_lahir' => '1990-01-01',
        'unit_kerja_id' => $unitB->id,
        'jabatan' => 'Staf',
        'status_karyawan' => 'PKWTT',
        'gaji_pokok' => 5000000,
    ]);

    expect(DB::table('karyawan')->where('nik', 'SAMA-001')->count())->toBe(2)
        ->and(DB::table('karyawan')->where('nomor_ktp', '1601010101010101')->count())->toBe(2);
});

test('legacy global custom role cannot be assigned to a user', function () {
    $koperasi = Koperasi::create(['nama' => 'Primer Aman']);
    $target = staffUser(['koperasi_id' => $koperasi->id]);
    $originalRoleId = $target->roles()->firstOrFail()->id;

    $legacyRole = new Role(['name' => 'Legacy Global', 'guard_name' => 'web']);
    $legacyRole->koperasi_id = null;
    $legacyRole->save();

    $this->actingAs(superAdminUser())
        ->from(route('pengguna.edit', $target))
        ->put(route('pengguna.update', $target), [
            'name' => $target->name,
            'email' => $target->email,
            'role_id' => $legacyRole->id,
        ])
        ->assertRedirect(route('pengguna.edit', $target))
        ->assertSessionHas('error');

    $target->refresh()->load('roles');
    expect($target->koperasi_id)->toBe($koperasi->id)
        ->and($target->roles->pluck('id')->all())->toBe([$originalRoleId]);
});
