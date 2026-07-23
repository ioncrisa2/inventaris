<?php

use App\Exports\AbsensiExport;
use App\Models\Absensi;
use App\Models\Karyawan;
use App\Models\UnitKerja;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;

uses(RefreshDatabase::class);

describe('laporan absensi', function () {
    it('can display the absensi report page for authenticated users', function () {
        $user = adminUser();

        $response = $this->actingAs($user)->get(route('laporan.absensi'));

        $response->assertOk();
        $response->assertViewIs('laporan.absensi');
    });

    it('can display formatted printable absensi report with status summaries', function () {
        $user = adminUser();
        $unitKerja = UnitKerja::create(['nama_unit' => 'IT']);
        $karyawan = Karyawan::create([
            'nik' => 'EMP-001',
            'nama_lengkap' => 'Budi Aktif',
            'tanggal_lahir' => '1990-01-01',
            'unit_kerja_id' => $unitKerja->id,
            'jabatan' => 'Staf IT',
            'status_karyawan' => 'Tetap',
            'gaji_pokok' => 7000000,
        ]);

        Absensi::create([
            'karyawan_id' => $karyawan->id,
            'tanggal' => '2026-07-01',
            'status' => 'Hadir',
        ]);

        Absensi::create([
            'karyawan_id' => $karyawan->id,
            'tanggal' => '2026-07-02',
            'status' => 'Izin',
            'catatan' => 'Urusan keluarga',
        ]);

        Absensi::create([
            'karyawan_id' => $karyawan->id,
            'tanggal' => '2026-07-03',
            'status' => 'Cuti',
        ]);

        Absensi::create([
            'karyawan_id' => $karyawan->id,
            'tanggal' => '2026-07-04',
            'status' => 'Dinas Luar Kota',
        ]);

        $this->actingAs($user)
            ->get(route('laporan.absensi.cetak', [
                'karyawan_id' => $karyawan->id,
                'bulan' => 7,
                'tahun' => 2026,
            ]))
            ->assertOk()
            ->assertViewIs('laporan.cetak.absensi')
            ->assertSee('Cetak Laporan Absensi')
            ->assertSee('Budi Aktif')
            ->assertSee('Urusan keluarga')
            ->assertViewHas('totalHadir', 1)
            ->assertViewHas('totalIzin', 1)
            ->assertViewHas('totalCuti', 1)
            ->assertViewHas('totalDinasLuarKota', 1);
    });

    it('can export the absensi report to excel with the selected filters', function () {
        $user = adminUser();
        $unitKerja = UnitKerja::create(['nama_unit' => 'IT']);
        $karyawan = Karyawan::create([
            'nik' => 'EMP-001',
            'nama_lengkap' => 'Budi Aktif',
            'tanggal_lahir' => '1990-01-01',
            'unit_kerja_id' => $unitKerja->id,
            'jabatan' => 'Staf IT',
            'status_karyawan' => 'Tetap',
            'gaji_pokok' => 7000000,
        ]);

        Absensi::create([
            'karyawan_id' => $karyawan->id,
            'tanggal' => '2026-07-01',
            'status' => 'Hadir',
        ]);

        Excel::fake();

        $this->actingAs($user)
            ->get(route('laporan.absensi.export', ['bulan' => 7, 'tahun' => 2026]))
            ->assertOk();

        Excel::assertDownloaded('laporan-absensi.xlsx', function (AbsensiExport $export) {
            return $export->collection()->count() === 1
                && $export->collection()->first()->status === 'Hadir';
        });
    });
});
