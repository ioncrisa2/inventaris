<?php

use App\Models\Absensi;
use App\Models\HariLibur;
use App\Models\Karyawan;
use App\Models\Koperasi;
use App\Models\KomponenGaji;
use App\Models\Role;
use App\Models\TransaksiGaji;
use App\Models\UnitKerja;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Siapkan dua koperasi, masing-masing dengan 1 baris data di modul inti
 * (unit kerja, karyawan, absensi, hari libur, komponen gaji, transaksi
 * gaji), dipakai bareng oleh semua test isolasi per-modul di file ini.
 *
 * @return array{koperasiA: Koperasi, koperasiB: Koperasi, adminA: \App\Models\User, adminB: \App\Models\User}
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
    $hariLibur = HariLibur::create(['tanggal' => "2026-0".(($label === 'A') ? '8' : '9')."-17", 'keterangan' => "Libur {$label}"]);
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
    ['adminA' => $adminA, 'adminB' => $adminB] = seedTwoTenants();

    $this->actingAs($adminA);
    $this->get(route('pengguna.index'))
        ->assertOk()
        ->assertSee($adminA->name)
        ->assertDontSee($adminB->email);

    $this->get(route('role.index'))
        ->assertOk()
        ->assertDontSee('admin_primer'); // hanya label ramah "Admin Primer" yang boleh muncul, bukan role milik tenant lain dengan nama lain
});

test('super_admin can see business data across every koperasi', function () {
    ['adminA' => $adminA, 'adminB' => $adminB] = seedTwoTenants();

    $this->actingAs($adminA);
    $dataA = seedCoreDataAs('A');

    $this->actingAs($adminB);
    $dataB = seedCoreDataAs('B');

    $this->actingAs(adminUser());

    expect(Karyawan::count())->toBe(2)
        ->and(Karyawan::pluck('id')->sort()->values()->all())
        ->toBe(collect([$dataA['karyawan']->id, $dataB['karyawan']->id])->sort()->values()->all())
        ->and(TransaksiGaji::count())->toBe(2)
        ->and(HariLibur::count())->toBe(2)
        ->and(KomponenGaji::count())->toBe(2);

    $this->get(route('karyawan.show', $dataA['karyawan']))->assertOk();
    $this->get(route('karyawan.show', $dataB['karyawan']))->assertOk();
});

test('admin_primer cannot create a new role, delete a role, or assign the super_admin/admin_primer role to any user', function () {
    ['adminA' => $adminA] = seedTwoTenants();
    $this->actingAs($adminA);

    $this->post(route('role.store'), [
        'name' => 'Role Bayangan',
        'permissions' => ['barang.view'],
    ])->assertForbidden();
    $this->assertDatabaseMissing('roles', ['name' => 'Role Bayangan']);

    $ownRole = \App\Models\Role::where('koperasi_id', $adminA->koperasi_id)->where('name', 'admin_primer')->firstOrFail();
    $this->delete(route('role.destroy', $ownRole))->assertForbidden();
    $this->assertDatabaseHas('roles', ['id' => $ownRole->id]);

    $target = \App\Models\User::factory()->create(['koperasi_id' => $adminA->koperasi_id]);

    $this->put(route('pengguna.update', $target), [
        'name' => $target->name,
        'email' => $target->email,
        'role' => 'super_admin',
    ])->assertRedirect();
    expect($target->fresh()->hasRole('super_admin'))->toBeFalse();

    $this->put(route('pengguna.update', $target), [
        'name' => $target->name,
        'email' => $target->email,
        'role' => 'admin_primer',
    ])->assertRedirect();
    expect($target->fresh()->hasRole('admin_primer'))->toBeFalse();
});

test('super_admin can provision a custom role for one specific koperasi, and only that koperasi can see or assign it', function () {
    ['koperasiA' => $koperasiA, 'adminA' => $adminA, 'adminB' => $adminB] = seedTwoTenants();

    $this->actingAs(adminUser());
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

    $kasir = \App\Models\User::factory()->create(['koperasi_id' => $koperasiA->id]);
    $this->put(route('pengguna.update', $kasir), [
        'name' => $kasir->name,
        'email' => $kasir->email,
        'role' => 'Kasir',
    ])->assertRedirect(route('pengguna.index'));
    expect($kasir->fresh()->hasRole($kasirRole))->toBeTrue();

    // admin_primer koperasi B sama sekali tidak melihat role "Kasir" milik koperasi A.
    $this->actingAs($adminB);
    $this->get(route('role.index'))->assertOk()->assertDontSee('Kasir');
    $this->get(route('pengguna.create'))->assertOk()->assertDontSee('Kasir');
    $this->get(route('role.edit', $kasirRole))->assertNotFound();
});
