<?php

use App\Models\Barang;
use App\Models\HariLibur;
use App\Models\Karyawan;
use App\Models\KomponenGaji;
use App\Models\Koperasi;
use App\Models\TransaksiGaji;
use App\Models\UnitKerja;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createTenantUiFixture(Koperasi $koperasi, string $suffix): array
{
    $admin = adminPrimerUser($koperasi);
    test()->actingAs($admin);

    $unit = UnitKerja::create([
        'nama_unit' => 'Unit Bersama',
        'kode' => 'U'.$suffix,
    ]);
    $karyawan = Karyawan::create([
        'nik' => 'NIK-'.$suffix,
        'nama_lengkap' => 'Karyawan '.$suffix,
        'tanggal_lahir' => '1990-01-01',
        'unit_kerja_id' => $unit->id,
        'jabatan' => 'Staf',
        'status_karyawan' => 'PKWTT',
        'gaji_pokok' => 5000000,
    ]);
    $barang = Barang::create([
        'kode_barang' => 'BRG-'.$suffix,
        'nama_barang' => 'Barang '.$suffix,
        'kategori' => 'Bukan Bangunan - Kelompok 1',
        'jenis_barang' => 'Access point Wi-Fi',
        'unit_kerja_id' => $unit->id,
        'tanggal_perolehan' => '2026-01-01',
        'harga_perolehan' => 1000000,
    ]);
    $hariLibur = HariLibur::create([
        'tanggal' => '2026-08-'.($suffix === 'A' ? '17' : '18'),
        'keterangan' => 'Libur '.$suffix,
    ]);
    $komponen = KomponenGaji::create([
        'nama_komponen' => 'Tunjangan '.$suffix,
        'jenis' => 'Tunjangan',
        'metode_perhitungan' => 'nominal_tetap',
        'nilai_default' => 100000,
    ]);
    $transaksi = TransaksiGaji::create([
        'karyawan_id' => $karyawan->id,
        'bulan' => 7,
        'tahun' => 2026,
        'gaji_pokok' => 5000000,
        'gaji_bersih' => 5000000,
    ]);

    return compact('admin', 'unit', 'karyawan', 'barang', 'hariLibur', 'komponen', 'transaksi');
}

test('super admin operational UI is explicitly global read only and tenant-qualified', function () {
    $koperasiA = Koperasi::create(['nama' => 'Primer Alpha']);
    $koperasiB = Koperasi::create(['nama' => 'Primer Beta']);
    $dataA = createTenantUiFixture($koperasiA, 'A');
    $dataB = createTenantUiFixture($koperasiB, 'B');

    $superAdmin = superAdminUser();

    $barangResponse = $this->actingAs($superAdmin)->get(route('barang.index'));
    $barangResponse
        ->assertOk()
        ->assertSee('Seluruh koperasi')
        ->assertSee('Baca data')
        ->assertSee('Primer Alpha')
        ->assertSee('Primer Beta')
        ->assertSee('Primer Alpha — Unit Bersama')
        ->assertSee('Primer Beta — Unit Bersama')
        ->assertDontSee(route('barang.create'), false)
        ->assertDontSee(route('barang.edit', $dataA['barang']), false)
        ->assertDontSee('data-delete-url="'.route('barang.destroy', $dataA['barang']).'"', false);

    $this->get(route('unit-kerja.index'))
        ->assertOk()
        ->assertDontSee('createUnitKerjaModal')
        ->assertDontSee(route('unit-kerja.update', $dataA['unit']), false)
        ->assertDontSee(route('unit-kerja.destroy', $dataA['unit']), false);

    $this->get(route('absensi.show', $dataA['karyawan']))
        ->assertOk()
        ->assertSee('Primer Alpha')
        ->assertDontSee('modalAbsensi');

    $this->get(route('hari-libur.tahun', ['tahun' => 2026]))
        ->assertOk()
        ->assertDontSee('createHariLiburModal')
        ->assertDontSee('editHariLiburModal')
        ->assertDontSee(route('hari-libur.destroy', $dataA['hariLibur']), false);

    $this->get(route('komponen-gaji.index'))
        ->assertOk()
        ->assertDontSee('createKomponenGajiModal')
        ->assertDontSee('editKomponenGajiModal')
        ->assertDontSee('data-delete-url="'.route('komponen-gaji.destroy', $dataA['komponen']).'"', false);

    $this->get(route('komponen-gaji.show', $dataA['komponen']))
        ->assertOk()
        ->assertSee('Primer Alpha')
        ->assertDontSee(route('komponen-gaji.edit', $dataA['komponen']), false)
        ->assertDontSee('data-delete-url="'.route('komponen-gaji.destroy', $dataA['komponen']).'"', false);

    $this->get(route('transaksi-gaji.show', $dataA['transaksi']))
        ->assertOk()
        ->assertSee('Primer Alpha')
        ->assertDontSee(route('transaksi-gaji.edit', $dataA['transaksi']), false);
});

test('super admin koperasi filter is precise while tenant user cannot widen its scope', function () {
    $koperasiA = Koperasi::create(['nama' => 'Primer Alpha']);
    $koperasiB = Koperasi::create(['nama' => 'Primer Beta']);
    $dataA = createTenantUiFixture($koperasiA, 'A');
    $dataB = createTenantUiFixture($koperasiB, 'B');

    $superAdmin = superAdminUser();

    $this->actingAs($superAdmin)
        ->get(route('barang.index', ['koperasi_id' => $koperasiA->id]))
        ->assertOk()
        ->assertSee($dataA['barang']->nama_barang)
        ->assertDontSee($dataB['barang']->nama_barang);

    $this->get(route('laporan.inventaris', ['koperasi_id' => $koperasiB->id]))
        ->assertOk()
        ->assertSee('Primer Beta')
        ->assertSee($dataB['barang']->nama_barang)
        ->assertDontSee($dataA['barang']->nama_barang);

    $this->actingAs($dataA['admin'])
        ->get(route('barang.index', ['koperasi_id' => $koperasiB->id]))
        ->assertOk()
        ->assertSee('Primer Alpha')
        ->assertSee($dataA['barang']->nama_barang)
        ->assertDontSee($dataB['barang']->nama_barang)
        ->assertDontSee('id="barang_koperasi_id"', false)
        ->assertSee(route('barang.create'), false);

    $this->get(route('laporan.inventaris', ['koperasi_id' => $koperasiB->id]))
        ->assertSessionHasErrors('koperasi_id');
});
