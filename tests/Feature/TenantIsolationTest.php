<?php

use App\Models\Absensi;
use App\Models\HariLibur;
use App\Models\Karyawan;
use App\Models\KomponenGaji;
use App\Models\Koperasi;
use App\Models\Role;
use App\Models\TransaksiGaji;
use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Siapkan dua koperasi, masing-masing dengan 1 baris data di modul inti
 * (unit kerja, karyawan, absensi, hari libur, komponen gaji, transaksi
 * gaji), dipakai bareng oleh semua test isolasi per-modul di file ini.
 *
 * @return array{koperasiA: Koperasi, koperasiB: Koperasi, adminA: User, adminB: User}
 */
function seedTwoTenants(): array
{
    $koperasiA = Koperasi::create(['nama' => 'Koperasi A']);
    $koperasiB = Koperasi::create(['nama' => 'Koperasi B']);
    $adminA = adminPrimerUser($koperasiA);
    $adminB = adminPrimerUser($koperasiB);

    return compact('koperasiA', 'koperasiB', 'adminA', 'adminB');
}

/** @return array{unitKerja: UnitKerja, karyawan: Karyawan, hariLibur: HariLibur, komponenGaji: KomponenGaji, transaksiGaji: TransaksiGaji} */
function seedCoreDataAs(string $label): array
{
    $unitKerja = UnitKerja::create(['nama_unit' => "Unit {$label}"]);
    $karyawan = Karyawan::create([
        'nik' => "{$label}-001",
        'nama_lengkap' => "Karyawan {$label}",
        'tanggal_lahir' => '1990-01-01',
        'unit_kerja_id' => $unitKerja->id,
        'jabatan' => 'Staf',
        'status_karyawan' => 'PKWTT',
        'gaji_pokok' => 5000000,
    ]);
    Absensi::create(['karyawan_id' => $karyawan->id, 'tanggal' => '2026-07-01', 'status' => 'Hadir']);
    $hariLibur = HariLibur::create(['tanggal' => '2026-0'.(($label === 'A') ? '8' : '9').'-17', 'keterangan' => "Libur {$label}"]);
    $komponenGaji = KomponenGaji::create([
        'nama_komponen' => "Tunjangan {$label}",
        'jenis' => 'Tunjangan',
        'metode_perhitungan' => 'nominal_tetap',
        'nilai_default' => 100000,
    ]);
    $transaksiGaji = TransaksiGaji::create([
        'karyawan_id' => $karyawan->id, 'bulan' => 7, 'tahun' => 2026,
        'gaji_pokok' => 5000000, 'gaji_bersih' => 5000000,
    ]);

    return compact('unitKerja', 'karyawan', 'hariLibur', 'komponenGaji', 'transaksiGaji');
}

test('admin_primer cannot see karyawan, absensi, hari libur, komponen gaji, or transaksi gaji of another koperasi', function () {
    ['adminA' => $adminA, 'adminB' => $adminB] = seedTwoTenants();

    test()->actingAs($adminA);
    $dataA = seedCoreDataAs('A');

    test()->actingAs($adminB);
    $dataB = seedCoreDataAs('B');

    expect(Karyawan::count())->toBe(1)->and(Karyawan::first()->id)->toBe($dataB['karyawan']->id)
        ->and(Absensi::count())->toBe(1)
        ->and(HariLibur::count())->toBe(1)->and(HariLibur::first()->id)->toBe($dataB['hariLibur']->id)
        ->and(KomponenGaji::count())->toBe(1)->and(KomponenGaji::first()->id)->toBe($dataB['komponenGaji']->id)
        ->and(TransaksiGaji::count())->toBe(1)->and(TransaksiGaji::first()->id)->toBe($dataB['transaksiGaji']->id);

    test()->actingAs($adminA);
    expect(Karyawan::count())->toBe(1)->and(Karyawan::first()->id)->toBe($dataA['karyawan']->id)
        ->and(HariLibur::count())->toBe(1)->and(HariLibur::first()->id)->toBe($dataA['hariLibur']->id)
        ->and(KomponenGaji::count())->toBe(1)->and(KomponenGaji::first()->id)->toBe($dataA['komponenGaji']->id)
        ->and(TransaksiGaji::count())->toBe(1)->and(TransaksiGaji::first()->id)->toBe($dataA['transaksiGaji']->id);
});

test('admin_primer cannot open another koperasi karyawan or transaksi gaji detail page', function () {
    ['adminA' => $adminA, 'adminB' => $adminB] = seedTwoTenants();

    test()->actingAs($adminA);
    $dataA = seedCoreDataAs('A');

    test()->actingAs($adminB);
    test()->get(route('karyawan.show', $dataA['karyawan']))->assertNotFound();
    test()->get(route('transaksi-gaji.show', $dataA['transaksiGaji']))->assertNotFound();
});

test('admin_primer only sees their own koperasi users and roles in the pengguna module', function () {
    ['koperasiA' => $koperasiA, 'koperasiB' => $koperasiB, 'adminA' => $adminA, 'adminB' => $adminB] = seedTwoTenants();

    $roleA = new Role(['name' => 'Operator Primer A', 'guard_name' => 'web']);
    $roleA->koperasi_id = $koperasiA->id;
    $roleA->save();

    $roleB = new Role(['name' => 'Operator Primer B', 'guard_name' => 'web']);
    $roleB->koperasi_id = $koperasiB->id;
    $roleB->save();

    $adminPrimerRoleA = Role::where('name', 'admin_primer')
        ->where('koperasi_id', $koperasiA->id)
        ->firstOrFail();

    $this->actingAs($adminA);
    $this->get(route('pengguna.index'))
        ->assertOk()
        ->assertSee($adminA->name)
        ->assertDontSee($adminB->email);

    $this->get(route('role.index'))
        ->assertOk()
        ->assertSee('Lingkup '.$koperasiA->nama)
        ->assertSee('Admin Primer')
        ->assertSee($roleA->name)
        ->assertDontSee($roleB->name)
        ->assertSee(route('role.edit', $roleA), false)
        ->assertDontSee(route('role.edit', $adminPrimerRoleA), false)
        ->assertDontSee(route('role.edit', $roleB), false)
        ->assertViewHas('roles', function ($roles) use ($adminPrimerRoleA, $roleA) {
            return $roles->getCollection()->pluck('id')->sort()->values()->all()
                === collect([$adminPrimerRoleA->id, $roleA->id])->sort()->values()->all();
        });

    // Role sistem milik sendiri tetap terlihat tetapi terkunci.
    $this->get(route('role.edit', $adminPrimerRoleA))->assertForbidden();

    // Role custom tenant sendiri dapat diperbarui tanpa bisa dipindah tenant.
    $this->put(route('role.update', $roleA), [
        'name' => 'Operator A Diperbarui',
        'koperasi_id' => $koperasiB->id,
        'permissions' => ['barang.view'],
    ])->assertRedirect(route('role.index'));
    expect($roleA->fresh()->name)->toBe('Operator A Diperbarui')
        ->and($roleA->fresh()->koperasi_id)->toBe($koperasiA->id);

    // Role tenant lain tidak dapat dibuka atau diperbarui lewat URL langsung.
    $this->get(route('role.edit', $roleB))->assertNotFound();
    $this->put(route('role.update', $roleB), [
        'name' => 'Percobaan Ambil Alih',
        'permissions' => ['barang.view'],
    ])->assertForbidden();
    expect($roleB->fresh()->name)->toBe('Operator Primer B');
});

test('super_admin can see business data across every koperasi', function () {
    ['adminA' => $adminA, 'adminB' => $adminB] = seedTwoTenants();

    $this->actingAs($adminA);
    $dataA = seedCoreDataAs('A');

    $this->actingAs($adminB);
    $dataB = seedCoreDataAs('B');

    $this->actingAs(superAdminUser());

    expect(Karyawan::count())->toBe(2)
        ->and(Karyawan::pluck('id')->sort()->values()->all())
        ->toBe(collect([$dataA['karyawan']->id, $dataB['karyawan']->id])->sort()->values()->all())
        ->and(TransaksiGaji::count())->toBe(2)
        ->and(HariLibur::count())->toBe(2)
        ->and(KomponenGaji::count())->toBe(2);

    $this->get(route('karyawan.show', $dataA['karyawan']))->assertOk();
    $this->get(route('karyawan.show', $dataB['karyawan']))->assertOk();
});

test('admin_primer creates roles only for their own koperasi but still cannot delete or assign system roles', function () {
    ['koperasiA' => $koperasiA, 'koperasiB' => $koperasiB, 'adminA' => $adminA] = seedTwoTenants();
    $this->actingAs($adminA);

    $this->get(route('role.index'))
        ->assertOk()
        ->assertSee('Tambah Role');
    $this->get(route('role.create'))
        ->assertOk()
        ->assertSee($koperasiA->nama)
        ->assertDontSee('name="koperasi_id"', false);

    $this->post(route('role.store'), [
        'name' => 'Petugas Primer A',
        // Percobaan memalsukan tenant tujuan harus ditimpa oleh server.
        'koperasi_id' => $koperasiB->id,
        'permissions' => ['barang.view'],
    ])->assertRedirect(route('role.index'));

    $this->assertDatabaseHas('roles', [
        'name' => 'Petugas Primer A',
        'koperasi_id' => $koperasiA->id,
    ]);
    $this->assertDatabaseMissing('roles', [
        'name' => 'Petugas Primer A',
        'koperasi_id' => $koperasiB->id,
    ]);

    $createdRole = Role::where('name', 'Petugas Primer A')->firstOrFail();
    expect($createdRole->permissions->pluck('name')->all())->toBe(['barang.view']);

    $ownRole = Role::where('koperasi_id', $adminA->koperasi_id)->where('name', 'admin_primer')->firstOrFail();
    $this->delete(route('role.destroy', $ownRole))->assertForbidden();
    $this->assertDatabaseHas('roles', ['id' => $ownRole->id]);

    $target = User::factory()->create(['koperasi_id' => $adminA->koperasi_id]);
    $superAdminRole = Role::query()
        ->where('name', 'super_admin')
        ->whereNull('koperasi_id')
        ->firstOrFail();

    $this->put(route('pengguna.update', $target), [
        'name' => $target->name,
        'email' => $target->email,
        'role_id' => $superAdminRole->id,
    ])->assertRedirect();
    expect($target->fresh()->hasRole('super_admin'))->toBeFalse();

    $this->put(route('pengguna.update', $target), [
        'name' => $target->name,
        'email' => $target->email,
        'role_id' => $ownRole->id,
    ])->assertRedirect();
    expect($target->fresh()->hasRole('admin_primer'))->toBeFalse();
});

test('super_admin can provision a custom role for one specific koperasi, and only that koperasi can see or assign it', function () {
    ['koperasiA' => $koperasiA, 'adminA' => $adminA, 'adminB' => $adminB] = seedTwoTenants();

    $this->actingAs(superAdminUser());
    $this->post(route('role.store'), [
        'name' => 'Kasir',
        'koperasi_id' => $koperasiA->id,
        'permissions' => ['barang.view', 'transaksi-gaji.view'],
    ])->assertRedirect(route('role.index'));

    $kasirRole = Role::where('koperasi_id', $koperasiA->id)->where('name', 'Kasir')->firstOrFail();

    // admin_primer koperasi A bisa lihat & pakai role custom ini di form Pengguna.
    $this->actingAs($adminA);
    $this->get(route('role.index'))->assertOk()->assertSee('Kasir');
    $this->get(route('pengguna.create'))->assertOk()->assertSee('Kasir');

    $kasir = User::factory()->create(['koperasi_id' => $koperasiA->id]);
    $this->put(route('pengguna.update', $kasir), [
        'name' => $kasir->name,
        'email' => $kasir->email,
        'role_id' => $kasirRole->id,
    ])->assertRedirect(route('pengguna.index'));
    expect($kasir->fresh()->hasRole($kasirRole))->toBeTrue();

    // admin_primer koperasi B sama sekali tidak melihat role "Kasir" milik koperasi A.
    $this->actingAs($adminB);
    $this->get(route('role.index'))->assertOk()->assertDontSee('Kasir');
    $this->get(route('pengguna.create'))->assertOk()->assertDontSee('Kasir');
    $this->get(route('role.edit', $kasirRole))->assertNotFound();
});
