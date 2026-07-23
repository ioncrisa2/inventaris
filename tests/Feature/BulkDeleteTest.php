<?php

use App\Models\Absensi;
use App\Models\Barang;
use App\Models\Karyawan;
use App\Models\KomponenGaji;
use App\Models\RiwayatKondisiBarang;
use App\Models\TransaksiGaji;
use App\Models\TransaksiGajiDetail;
use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = adminUser();
    $this->actingAs($this->admin);
});

function bulkTestKaryawan(UnitKerja $unitKerja, string $nik): Karyawan
{
    return Karyawan::create([
        'nik' => $nik,
        'nama_lengkap' => 'Karyawan '.$nik,
        'tanggal_lahir' => '1990-01-01',
        'unit_kerja_id' => $unitKerja->id,
        'jabatan' => 'Staf',
        'status_karyawan' => 'Tetap',
        'gaji_pokok' => 5000000,
    ]);
}

function bulkTestBarang(UnitKerja $unitKerja, string $kode): Barang
{
    return Barang::create([
        'kode_barang' => $kode,
        'nama_barang' => 'Barang '.$kode,
        'kategori' => 'Bukan Bangunan - Kelompok 1',
        'unit_kerja_id' => $unitKerja->id,
        'tanggal_perolehan' => '2026-01-01',
        'harga_perolehan' => 1000000,
    ]);
}

test('crud index tables expose permission-aware bulk selection', function () {
    $pages = [
        ['barang.index', 'barang.bulk-destroy', 'barang'],
        ['karyawan.index', 'karyawan.bulk-destroy', 'karyawan'],
        ['unit-kerja.index', 'unit-kerja.bulk-destroy', 'unit-kerja'],
        ['komponen-gaji.index', 'komponen-gaji.bulk-destroy', 'komponen-gaji'],
        ['transaksi-gaji.index', 'transaksi-gaji.bulk-destroy', 'transaksi-gaji'],
        ['pengguna.index', 'pengguna.bulk-destroy', 'pengguna'],
    ];

    foreach ($pages as [$indexRoute, $deleteRoute, $group]) {
        $this->get(route($indexRoute))
            ->assertOk()
            ->assertSee(route($deleteRoute), false)
            ->assertSee('data-bulk-select-all="'.$group.'"', false)
            ->assertSee('Hapus Terpilih');
    }
});

test('role management deliberately avoids bulk deletion controls', function () {
    $this->get(route('role.index'))
        ->assertOk()
        ->assertDontSee(route('role.bulk-destroy'), false)
        ->assertDontSee('data-bulk-select-all="role"', false);
});

test('selected barang are deleted while unselected barang remain', function () {
    $unit = UnitKerja::create(['nama_unit' => 'IT']);
    $selected = [
        bulkTestBarang($unit, 'BRG-001'),
        bulkTestBarang($unit, 'BRG-002'),
    ];
    $unselected = bulkTestBarang($unit, 'BRG-003');

    $this->delete(route('barang.bulk-destroy'), [
        'ids' => collect($selected)->pluck('id')->all(),
    ])->assertRedirect(route('barang.index'));

    $this->assertDatabaseMissing('barang', ['id' => $selected[0]->id]);
    $this->assertDatabaseMissing('barang', ['id' => $selected[1]->id]);
    $this->assertDatabaseHas('barang', ['id' => $unselected->id]);
});

test('bulk barang deletion is rejected atomically when one item has relations', function () {
    $unit = UnitKerja::create(['nama_unit' => 'IT']);
    $clean = bulkTestBarang($unit, 'BRG-CLEAN');
    $linked = bulkTestBarang($unit, 'BRG-LINKED');
    RiwayatKondisiBarang::create([
        'barang_id' => $linked->id,
        'tanggal_pemeriksaan' => '2026-07-21',
        'kondisi' => 'Baik',
    ]);

    $this->from(route('barang.index'))
        ->delete(route('barang.bulk-destroy'), ['ids' => [$clean->id, $linked->id]])
        ->assertRedirect(route('barang.index'))
        ->assertSessionHas('error');

    $this->assertDatabaseHas('barang', ['id' => $clean->id]);
    $this->assertDatabaseHas('barang', ['id' => $linked->id]);
});

test('clean records can be bulk deleted across every supported master table', function () {
    $unit = UnitKerja::create(['nama_unit' => 'Unit Sementara']);
    $karyawan = bulkTestKaryawan($unit, 'EMP-CLEAN');
    $transaksi = TransaksiGaji::create([
        'karyawan_id' => $karyawan->id,
        'bulan' => 6,
        'tahun' => 2026,
        'gaji_pokok' => 5000000,
        'gaji_bersih' => 5000000,
    ]);
    $komponen = KomponenGaji::create([
        'nama_komponen' => 'Komponen Sementara',
        'jenis' => 'Tunjangan',
        'metode_perhitungan' => 'nominal_tetap',
        'nilai_default' => 100000,
    ]);
    $user = User::factory()->create();
    $role = Role::create(['name' => 'Role Sementara', 'guard_name' => 'web']);

    $this->delete(route('transaksi-gaji.bulk-destroy'), ['ids' => [$transaksi->id]])
        ->assertRedirect(route('transaksi-gaji.index'));
    $this->delete(route('karyawan.bulk-destroy'), ['ids' => [$karyawan->id]])
        ->assertRedirect(route('karyawan.index'));
    $this->delete(route('unit-kerja.bulk-destroy'), ['ids' => [$unit->id]])
        ->assertRedirect(route('unit-kerja.index'));
    $this->delete(route('komponen-gaji.bulk-destroy'), ['ids' => [$komponen->id]])
        ->assertRedirect(route('komponen-gaji.index'));
    $this->delete(route('pengguna.bulk-destroy'), ['ids' => [$user->id]])
        ->assertRedirect(route('pengguna.index'));
    $this->delete(route('role.bulk-destroy'), ['ids' => [$role->id]])
        ->assertRedirect(route('role.index'));

    $this->assertDatabaseMissing('transaksi_gaji', ['id' => $transaksi->id]);
    $this->assertDatabaseMissing('karyawan', ['id' => $karyawan->id]);
    $this->assertDatabaseMissing('unit_kerja', ['id' => $unit->id]);
    $this->assertDatabaseMissing('komponen_gaji', ['id' => $komponen->id]);
    $this->assertDatabaseMissing('users', ['id' => $user->id]);
    $this->assertDatabaseMissing('roles', ['id' => $role->id]);
});

test('bulk delete validates that at least one row is selected', function () {
    $this->from(route('barang.index'))
        ->delete(route('barang.bulk-destroy'), [])
        ->assertRedirect(route('barang.index'))
        ->assertSessionHasErrors('ids');
});

test('unit kerja with related data rejects individual and atomic bulk deletion', function () {
    $linked = UnitKerja::create(['nama_unit' => 'Unit Terpakai']);
    $clean = UnitKerja::create(['nama_unit' => 'Unit Kosong']);
    bulkTestBarang($linked, 'BRG-LINKED');

    $this->from(route('unit-kerja.index'))
        ->delete(route('unit-kerja.destroy', $linked))
        ->assertRedirect(route('unit-kerja.index'))
        ->assertSessionHas('error');

    $this->from(route('unit-kerja.index'))
        ->delete(route('unit-kerja.bulk-destroy'), ['ids' => [$clean->id, $linked->id]])
        ->assertRedirect(route('unit-kerja.index'))
        ->assertSessionHas('error');

    $this->assertDatabaseHas('unit_kerja', ['id' => $linked->id]);
    $this->assertDatabaseHas('unit_kerja', ['id' => $clean->id]);
});

test('database also rejects deleting a business parent that is still referenced', function () {
    $unit = UnitKerja::create(['nama_unit' => 'Unit Dijaga Database']);
    bulkTestBarang($unit, 'BRG-FK-GUARD');

    expect(fn () => $unit->delete())
        ->toThrow(QueryException::class);

    $this->assertDatabaseHas('unit_kerja', ['id' => $unit->id]);
});

test('karyawan referenced by attendance cannot be deleted', function () {
    $unit = UnitKerja::create(['nama_unit' => 'SDM']);
    $karyawan = bulkTestKaryawan($unit, 'EMP-001');
    Absensi::create([
        'karyawan_id' => $karyawan->id,
        'tanggal' => '2026-07-01',
        'status' => 'Hadir',
    ]);

    $this->from(route('karyawan.index'))
        ->delete(route('karyawan.bulk-destroy'), ['ids' => [$karyawan->id]])
        ->assertRedirect(route('karyawan.index'))
        ->assertSessionHas('error');

    $this->assertDatabaseHas('karyawan', ['id' => $karyawan->id]);
});

test('komponen gaji referenced by transaction detail cannot be deleted', function () {
    $unit = UnitKerja::create(['nama_unit' => 'Keuangan']);
    $karyawan = bulkTestKaryawan($unit, 'EMP-002');
    $komponen = KomponenGaji::create([
        'nama_komponen' => 'Tunjangan Jabatan',
        'jenis' => 'Tunjangan',
        'metode_perhitungan' => 'nominal_tetap',
        'nilai_default' => 500000,
    ]);
    $transaksi = TransaksiGaji::create([
        'karyawan_id' => $karyawan->id,
        'bulan' => 7,
        'tahun' => 2026,
        'gaji_pokok' => 5000000,
        'gaji_bersih' => 5500000,
    ]);
    TransaksiGajiDetail::create([
        'transaksi_gaji_id' => $transaksi->id,
        'komponen_gaji_id' => $komponen->id,
        'nama_komponen_snapshot' => 'Tunjangan Jabatan',
        'jenis_snapshot' => 'Tunjangan',
        'metode_perhitungan_snapshot' => 'nominal_tetap',
        'nilai_snapshot' => 500000,
        'nominal_hasil' => 500000,
    ]);

    $this->delete(route('komponen-gaji.destroy', $komponen))->assertRedirect();

    $this->assertDatabaseHas('komponen_gaji', ['id' => $komponen->id]);
});

test('bulk user deletion rejects a selection containing the active account', function () {
    $other = User::factory()->create();

    $this->from(route('pengguna.index'))
        ->delete(route('pengguna.bulk-destroy'), ['ids' => [$other->id, $this->admin->id]])
        ->assertRedirect(route('pengguna.index'))
        ->assertSessionHas('error');

    $this->assertDatabaseHas('users', ['id' => $other->id]);
    $this->assertDatabaseHas('users', ['id' => $this->admin->id]);
});

test('bulk role deletion rejects roles still assigned to users', function () {
    $used = Role::findByName('Staff');
    $clean = Role::create(['name' => 'Role Kosong', 'guard_name' => 'web']);
    User::factory()->create()->assignRole($used);

    $this->from(route('role.index'))
        ->delete(route('role.bulk-destroy'), ['ids' => [$clean->id, $used->id]])
        ->assertRedirect(route('role.index'))
        ->assertSessionHas('error');

    $this->assertDatabaseHas('roles', ['id' => $used->id]);
    $this->assertDatabaseHas('roles', ['id' => $clean->id]);
});

test('staff without delete permission cannot use bulk delete endpoint', function () {
    $this->actingAs(staffUser());
    $unit = UnitKerja::create(['nama_unit' => 'Operasional']);
    $karyawan = bulkTestKaryawan($unit, 'EMP-003');

    $this->delete(route('karyawan.bulk-destroy'), ['ids' => [$karyawan->id]])
        ->assertForbidden();

    $this->assertDatabaseHas('karyawan', ['id' => $karyawan->id]);
});
