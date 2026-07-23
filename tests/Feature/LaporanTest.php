<?php

use App\Exports\InventarisExport;
use App\Exports\KepegawaianExport;
use App\Models\Barang;
use App\Models\Karyawan;
use App\Models\RiwayatKondisiBarang;
use App\Models\UnitKerja;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(adminUser());
    $this->unitIt = UnitKerja::create(['nama_unit' => 'IT']);
    $this->unitKeuangan = UnitKerja::create(['nama_unit' => 'Keuangan']);

    $laptop = Barang::create([
        'kode_barang' => 'INV-001',
        'nama_barang' => 'Laptop Operasional',
        'kategori' => 'Bukan Bangunan - Kelompok 1',
        'unit_kerja_id' => $this->unitIt->id,
        'tanggal_perolehan' => now()->subMonth(),
        'harga_perolehan' => 12000000,
    ]);

    $kursi = Barang::create([
        'kode_barang' => 'INV-002',
        'nama_barang' => 'Kursi Kantor',
        'kategori' => 'Bukan Bangunan - Kelompok 2',
        'unit_kerja_id' => $this->unitKeuangan->id,
        'tanggal_perolehan' => now()->subWeek(),
        'harga_perolehan' => 1000000,
    ]);

    RiwayatKondisiBarang::create([
        'barang_id' => $laptop->id,
        'tanggal_pemeriksaan' => now(),
        'kondisi' => 'Rusak Ringan',
    ]);

    RiwayatKondisiBarang::create([
        'barang_id' => $kursi->id,
        'tanggal_pemeriksaan' => now(),
        'kondisi' => 'Baik',
    ]);

    Karyawan::create([
        'nik' => 'EMP-001',
        'nama_lengkap' => 'Budi Aktif',
        'tanggal_lahir' => '1990-01-01',
        'unit_kerja_id' => $this->unitIt->id,
        'jabatan' => 'Staf IT',
        'status_karyawan' => 'Tetap',
        'gaji_pokok' => 7000000,
    ]);

    Karyawan::create([
        'nik' => 'EMP-002',
        'nama_lengkap' => 'Sari Honorer',
        'tanggal_lahir' => '1992-01-01',
        'unit_kerja_id' => $this->unitKeuangan->id,
        'jabatan' => 'Analis',
        'status_karyawan' => 'Honorer',
        'tanggal_mengundurkan_diri' => now()->subMonth()->toDateString(),
        'gaji_pokok' => 6000000,
    ]);
});

test('inventory report displays real summaries and records', function () {
    $this->get(route('laporan.inventaris'))
        ->assertOk()
        ->assertSee('Laptop Operasional')
        ->assertSee('Kursi Kantor')
        ->assertSee('Rp 13.000.000')
        ->assertViewHas('totalBarang', 2)
        ->assertViewHas('totalNilai', 13000000)
        ->assertViewHas('barangPerluPerbaikan', 1);
});

test('inventory report filters all summaries and detail records', function () {
    $this->get(route('laporan.inventaris', ['kategori' => 'Bukan Bangunan - Kelompok 1']))
        ->assertOk()
        ->assertSee('Laptop Operasional')
        ->assertDontSee('Kursi Kantor')
        ->assertViewHas('totalBarang', 1)
        ->assertViewHas('totalNilai', 12000000)
        ->assertViewHas('barangPerluPerbaikan', 1);
});

test('inventory print report uses selected filters and print layout', function () {
    $this->get(route('laporan.inventaris.cetak', ['kategori' => 'Bukan Bangunan - Kelompok 1']))
        ->assertOk()
        ->assertViewIs('laporan.cetak.inventaris')
        ->assertSee('Cetak Laporan Inventaris')
        ->assertSee('Laptop Operasional')
        ->assertDontSee('Kursi Kantor')
        ->assertSee('Rp 12.000.000');
});

test('employee report displays real summaries and records', function () {
    $this->get(route('laporan.kepegawaian'))
        ->assertOk()
        ->assertSee('Budi Aktif')
        ->assertSee('Sari Honorer')
        ->assertSee('Rp 7.000.000')
        ->assertViewHas('totalKaryawan', 2)
        ->assertViewHas('totalAktif', 1)
        ->assertViewHas('totalGajiAktif', 7000000);
});

test('employee report filters summaries and detail records', function () {
    // Filter by status_karyawan narrows the list; "aktif" itself is
    // determined independently by tanggal_mengundurkan_diri, not by status —
    // Sari sudah keluar jadi tetap dihitung tidak aktif meski status filter
    // yang dipilih cocok dengan dia.
    $this->get(route('laporan.kepegawaian', ['status_karyawan' => 'Honorer']))
        ->assertOk()
        ->assertSee('Sari Honorer')
        ->assertDontSee('Budi Aktif')
        ->assertViewHas('totalKaryawan', 1)
        ->assertViewHas('totalAktif', 0)
        ->assertViewHas('totalGajiAktif', 0);
});

test('employee print report uses selected filters and print layout', function () {
    $this->get(route('laporan.kepegawaian.cetak', ['status_karyawan' => 'Honorer']))
        ->assertOk()
        ->assertViewIs('laporan.cetak.kepegawaian')
        ->assertSee('Cetak Laporan Kepegawaian')
        ->assertSee('Sari Honorer')
        ->assertDontSee('Budi Aktif')
        ->assertSee('Karyawan Mengundurkan Diri');
});

test('inventory report can be exported to excel with the selected filters', function () {
    Excel::fake();

    $this->get(route('laporan.inventaris.export', ['kategori' => 'Bukan Bangunan - Kelompok 1']))->assertOk();

    Excel::assertDownloaded('laporan-inventaris.xlsx', function (InventarisExport $export) {
        return $export->collection()->count() === 1
            && $export->collection()->first()->nama_barang === 'Laptop Operasional';
    });
});

test('employee report can be exported to excel with the selected filters', function () {
    Excel::fake();

    $this->get(route('laporan.kepegawaian.export', ['status_karyawan' => 'Honorer']))->assertOk();

    Excel::assertDownloaded('laporan-kepegawaian.xlsx', function (KepegawaianExport $export) {
        return $export->collection()->count() === 1
            && $export->collection()->first()->nama_lengkap === 'Sari Honorer';
    });
});

test('all report pages use the same restrained summary grid', function () {
    foreach ([
        'laporan.inventaris',
        'laporan.kepegawaian',
        'laporan.absensi',
        'laporan.penggajian',
    ] as $routeName) {
        $this->get(route($routeName))
            ->assertOk()
            ->assertSee('report-stat-grid', false)
            ->assertSee('summary-card--plain', false);
    }
});

test('attendance and payroll reports localize month names to Indonesian', function () {
    foreach (['laporan.absensi', 'laporan.penggajian'] as $routeName) {
        $this->get(route($routeName, ['bulan' => 7, 'tahun' => 2026]))
            ->assertOk()
            ->assertSee('Juli')
            ->assertDontSee('July');
    }
});
