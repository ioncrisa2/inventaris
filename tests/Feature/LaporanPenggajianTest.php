<?php

use App\Exports\PenggajianExport;
use App\Models\Karyawan;
use App\Models\TransaksiGaji;
use App\Models\TransaksiGajiDetail;
use App\Models\UnitKerja;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(adminUser());

    $this->unitIt = UnitKerja::create(['nama_unit' => 'IT']);
    $this->unitKeuangan = UnitKerja::create(['nama_unit' => 'Keuangan']);

    $this->budi = Karyawan::create([
        'nik' => 'EMP-001',
        'nama_lengkap' => 'Budi Santoso',
        'tanggal_lahir' => '1990-01-01',
        'unit_kerja_id' => $this->unitIt->id,
        'jabatan' => 'Staf IT',
        'status_karyawan' => 'Tetap',
        'gaji_pokok' => 5000000,
    ]);

    $this->sari = Karyawan::create([
        'nik' => 'EMP-002',
        'nama_lengkap' => 'Sari Utami',
        'tanggal_lahir' => '1992-01-01',
        'unit_kerja_id' => $this->unitKeuangan->id,
        'jabatan' => 'Staf Keuangan',
        'status_karyawan' => 'Tetap',
        'gaji_pokok' => 6000000,
    ]);

    $transaksiBudi = TransaksiGaji::create([
        'karyawan_id' => $this->budi->id,
        'bulan' => 7,
        'tahun' => 2026,
        'gaji_pokok' => 5000000,
        'gaji_bersih' => 5400000,
    ]);
    TransaksiGajiDetail::create([
        'transaksi_gaji_id' => $transaksiBudi->id,
        'nama_komponen_snapshot' => 'Tunjangan Jabatan',
        'jenis_snapshot' => 'Tunjangan',
        'metode_perhitungan_snapshot' => 'nominal_tetap',
        'nilai_snapshot' => 500000,
        'nominal_hasil' => 500000,
    ]);
    TransaksiGajiDetail::create([
        'transaksi_gaji_id' => $transaksiBudi->id,
        'nama_komponen_snapshot' => 'Potongan BPJS',
        'jenis_snapshot' => 'Potongan',
        'metode_perhitungan_snapshot' => 'nominal_tetap',
        'nilai_snapshot' => 100000,
        'nominal_hasil' => 100000,
    ]);

    TransaksiGaji::create([
        'karyawan_id' => $this->sari->id,
        'bulan' => 7,
        'tahun' => 2026,
        'gaji_pokok' => 6000000,
        'gaji_bersih' => 6000000,
    ]);

    // Transaksi bulan lain, dipakai untuk memastikan filter periode benar-benar bekerja.
    TransaksiGaji::create([
        'karyawan_id' => $this->budi->id,
        'bulan' => 6,
        'tahun' => 2026,
        'gaji_pokok' => 5000000,
        'gaji_bersih' => 5000000,
    ]);
});

test('payroll report displays real summaries and records for the selected period', function () {
    $this->get(route('laporan.penggajian', ['bulan' => 7, 'tahun' => 2026]))
        ->assertOk()
        ->assertSee('Budi Santoso')
        ->assertSee('Sari Utami')
        ->assertViewHas('totalTransaksi', 2)
        ->assertViewHas('totalGajiPokok', 11000000)
        ->assertViewHas('totalTunjangan', 500000)
        ->assertViewHas('totalPotongan', 100000)
        ->assertViewHas('totalGajiBersih', 11400000);
});

test('payroll report filters by unit kerja', function () {
    $this->get(route('laporan.penggajian', [
        'bulan' => 7,
        'tahun' => 2026,
        'unit_kerja_id' => $this->unitIt->id,
    ]))
        ->assertOk()
        ->assertSee('Budi Santoso')
        ->assertDontSee('Sari Utami')
        ->assertViewHas('totalTransaksi', 1)
        ->assertViewHas('totalGajiBersih', 5400000);
});

test('payroll print report uses selected filters and print layout', function () {
    $this->get(route('laporan.penggajian.cetak', ['bulan' => 7, 'tahun' => 2026]))
        ->assertOk()
        ->assertViewIs('laporan.cetak.penggajian')
        ->assertSee('Cetak Laporan Penggajian')
        ->assertSee('Budi Santoso')
        ->assertSee('Sari Utami');
});

test('payroll report can be exported to excel with the selected filters', function () {
    Excel::fake();

    $this->get(route('laporan.penggajian.export', [
        'bulan' => 7,
        'tahun' => 2026,
        'unit_kerja_id' => $this->unitIt->id,
    ]))->assertOk();

    Excel::assertDownloaded('laporan-penggajian.xlsx', function (PenggajianExport $export) {
        return $export->collection()->count() === 1
            && $export->collection()->first()->karyawan->nama_lengkap === 'Budi Santoso';
    });
});

test('staff without payroll report permission cannot access it', function () {
    // Role Staff default punya laporan.penggajian.view, jadi buat role baru
    // tanpa permission sama sekali untuk memverifikasi endpoint benar-benar
    // digembok permission, bukan cuma disembunyikan di menu.
    $this->actingAs(adminUser())->post(route('role.store'), [
        'name' => 'Tanpa Akses Laporan',
        'permissions' => [],
    ]);

    $userTanpaAkses = staffUser(['email' => 'tanpa-akses@example.com']);
    $userTanpaAkses->syncRoles(['Tanpa Akses Laporan']);

    $this->actingAs($userTanpaAkses)
        ->get(route('laporan.penggajian'))
        ->assertForbidden();
});
