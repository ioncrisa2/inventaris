<?php

use App\Exports\PenyusutanExport;
use App\Models\Barang;
use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(adminUser());
    $this->unitIt = UnitKerja::create(['nama_unit' => 'IT']);
    $this->unitKeuangan = UnitKerja::create(['nama_unit' => 'Keuangan']);

    // Kelompok 1 = 4 tahun (48 bulan): Rp4.800.000 / 48 = Rp100.000/bulan.
    // Perolehan Januari 2020 -> tahun 2020 disusutkan penuh 12 bulan = Rp1.200.000.
    $this->laptop = Barang::create([
        'kode_barang' => 'INV-001',
        'nama_barang' => 'Laptop Operasional',
        'kategori' => 'Bukan Bangunan - Kelompok 1',
        'unit_kerja_id' => $this->unitIt->id,
        'tanggal_perolehan' => '2020-01-15',
        'harga_perolehan' => 4800000,
    ]);

    // Kelompok 2 = 8 tahun (96 bulan): Rp9.600.000 / 96 = Rp100.000/bulan.
    $this->kendaraan = Barang::create([
        'kode_barang' => 'INV-002',
        'nama_barang' => 'Mobil Operasional',
        'kategori' => 'Bukan Bangunan - Kelompok 2',
        'unit_kerja_id' => $this->unitKeuangan->id,
        'tanggal_perolehan' => '2020-01-15',
        'harga_perolehan' => 9600000,
    ]);
});

test('penyusutan report displays real summaries and records for the selected tahun', function () {
    $this->get(route('laporan.penyusutan', ['tahun' => 2020]))
        ->assertOk()
        ->assertSee('Laptop Operasional')
        ->assertSee('Mobil Operasional')
        ->assertSee('Rp 1.200.000')
        ->assertViewHas('totalAset', 2)
        ->assertViewHas('totalHargaPerolehan', '14400000.00')
        ->assertViewHas('totalPenyusutanTahunIni', '2400000.00');
});

test('penyusutan report filters by golongan and unit kerja', function () {
    $this->get(route('laporan.penyusutan', ['tahun' => 2020, 'kategori' => 'Bukan Bangunan - Kelompok 1']))
        ->assertOk()
        ->assertSee('Laptop Operasional')
        ->assertDontSee('Mobil Operasional')
        ->assertViewHas('totalAset', 1)
        ->assertViewHas('totalPenyusutanTahunIni', '1200000.00');
});

test('penyusutan report excludes assets acquired after the report tahun', function () {
    Barang::create([
        'kode_barang' => 'INV-003',
        'nama_barang' => 'Printer Baru',
        'kategori' => 'Bukan Bangunan - Kelompok 1',
        'unit_kerja_id' => $this->unitIt->id,
        'tanggal_perolehan' => '2021-06-01',
        'harga_perolehan' => 2000000,
    ]);

    $this->get(route('laporan.penyusutan', ['tahun' => 2020]))
        ->assertOk()
        ->assertDontSee('Printer Baru');
});

test('penyusutan print report shows the same figures as the tahun requested', function () {
    $this->get(route('laporan.penyusutan.cetak', ['tahun' => 2020]))
        ->assertOk()
        ->assertViewIs('laporan.cetak.penyusutan')
        ->assertSee('Laporan Penyusutan')
        ->assertSee('Laptop Operasional')
        ->assertSee('Rp 1.200.000');
});

test('penyusutan report can be exported to excel with the selected tahun', function () {
    Excel::fake();

    $this->get(route('laporan.penyusutan.export', ['tahun' => 2020, 'kategori' => 'Bukan Bangunan - Kelompok 1']))->assertOk();

    Excel::assertDownloaded('laporan-penyusutan.xlsx', function (PenyusutanExport $export) {
        $rows = $export->collection();

        return $rows->count() === 1
            && $rows->first()['barang']->nama_barang === 'Laptop Operasional'
            && $rows->first()['penyusutan_tahun_ini'] === '1200000.00';
    });
});

test('staff with laporan.penyusutan.view permission can access the report', function () {
    $this->actingAs(staffUser());

    $this->get(route('laporan.penyusutan'))->assertOk();
});

test('user without laporan.penyusutan.view permission is forbidden', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('laporan.penyusutan'))->assertForbidden();
});
