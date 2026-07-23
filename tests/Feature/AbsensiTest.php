<?php

use App\Models\Absensi;
use App\Models\Karyawan;
use App\Models\UnitKerja;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(adminUser());
    $this->unitKerja = UnitKerja::create(['nama_unit' => 'IT']);
});

describe('absensi index', function () {
    it('displays and filters karyawan by search', function () {
        $aktif = Karyawan::create([
            'nik' => 'EMP-001',
            'nama_lengkap' => 'Budi Aktif',
            'tanggal_lahir' => '1990-01-01',
            'unit_kerja_id' => $this->unitKerja->id,
            'jabatan' => 'Staf IT',
            'status_karyawan' => 'Tetap',
            'gaji_pokok' => 7000000,
        ]);

        Karyawan::create([
            'nik' => 'EMP-002',
            'nama_lengkap' => 'Sari Utami',
            'tanggal_lahir' => '1992-01-01',
            'unit_kerja_id' => $this->unitKerja->id,
            'jabatan' => 'Analis',
            'status_karyawan' => 'Tetap',
            'gaji_pokok' => 6000000,
        ]);

        $this->get(route('absensi.index'))
            ->assertOk()
            ->assertSee('Budi Aktif')
            ->assertSee('Sari Utami');

        $this->get(route('absensi.index', ['search' => 'Budi']))
            ->assertOk()
            ->assertSee('Budi Aktif')
            ->assertDontSee('Sari Utami');

        $this->get(route('absensi.index'))
            ->assertSee(route('absensi.show', $aktif), false);
    });

    it('still lists karyawan who have already left', function () {
        Karyawan::create([
            'nik' => 'EMP-003',
            'nama_lengkap' => 'Eko Sudah Keluar',
            'tanggal_lahir' => '1991-01-01',
            'unit_kerja_id' => $this->unitKerja->id,
            'jabatan' => 'Staf Umum',
            'status_karyawan' => 'Honorer',
            'tanggal_mengundurkan_diri' => now()->subMonth()->toDateString(),
            'gaji_pokok' => 4800000,
        ]);

        $this->get(route('absensi.index'))
            ->assertOk()
            ->assertSee('Eko Sudah Keluar');
    });
});

describe('absensi show (kalender)', function () {
    it('renders the calendar for the requested month and year with correct totals', function () {
        $karyawan = Karyawan::create([
            'nik' => 'EMP-001',
            'nama_lengkap' => 'Budi Aktif',
            'tanggal_lahir' => '1990-01-01',
            'unit_kerja_id' => $this->unitKerja->id,
            'jabatan' => 'Staf IT',
            'status_karyawan' => 'Tetap',
            'gaji_pokok' => 7000000,
        ]);

        Absensi::create(['karyawan_id' => $karyawan->id, 'tanggal' => '2026-03-02', 'status' => 'Hadir']);
        Absensi::create(['karyawan_id' => $karyawan->id, 'tanggal' => '2026-03-03', 'status' => 'Izin']);
        Absensi::create(['karyawan_id' => $karyawan->id, 'tanggal' => '2026-03-04', 'status' => 'Cuti']);
        Absensi::create(['karyawan_id' => $karyawan->id, 'tanggal' => '2026-03-05', 'status' => 'Dinas Luar Kota']);
        // Entri di bulan lain tidak boleh ikut terhitung.
        Absensi::create(['karyawan_id' => $karyawan->id, 'tanggal' => '2026-04-01', 'status' => 'Sakit']);

        $namaBulan = Carbon::createFromDate(2026, 3, 1)->translatedFormat('F');

        $this->get(route('absensi.show', ['karyawan' => $karyawan, 'bulan' => 3, 'tahun' => 2026]))
            ->assertOk()
            ->assertSee('Budi Aktif')
            ->assertSee($namaBulan.' 2026')
            ->assertSee('Cuti')
            ->assertSee('Dinas Luar Kota')
            ->assertSee('Hari libur (Minggu)')
            ->assertSee('Di luar bulan')
            ->assertSee('calendar-cell-holiday', false)
            ->assertViewHas('totalHadir', 1)
            ->assertViewHas('totalIzin', 1)
            ->assertViewHas('totalSakit', 0)
            ->assertViewHas('totalCuti', 1)
            ->assertViewHas('totalDinasLuarKota', 1);
    });

    it('defaults to the current month and year when none are given', function () {
        $karyawan = Karyawan::create([
            'nik' => 'EMP-001',
            'nama_lengkap' => 'Budi Aktif',
            'tanggal_lahir' => '1990-01-01',
            'unit_kerja_id' => $this->unitKerja->id,
            'jabatan' => 'Staf IT',
            'status_karyawan' => 'Tetap',
            'gaji_pokok' => 7000000,
        ]);

        $this->get(route('absensi.show', $karyawan))
            ->assertOk()
            ->assertViewHas('bulan', now()->month)
            ->assertViewHas('tahun', now()->year);
    });
});

describe('absensi store', function () {
    it('creates a new entry and updates it when the same date is submitted again', function () {
        $karyawan = Karyawan::create([
            'nik' => 'EMP-001',
            'nama_lengkap' => 'Budi Aktif',
            'tanggal_lahir' => '1990-01-01',
            'unit_kerja_id' => $this->unitKerja->id,
            'jabatan' => 'Staf IT',
            'status_karyawan' => 'Tetap',
            'gaji_pokok' => 7000000,
        ]);

        // Mundur 2 hari jika kemarin kebetulan hari Minggu: controller hanya
        // mengizinkan status Izin/Sakit untuk tanggal hari Minggu, sedangkan
        // test ini sengaja menguji status Hadir.
        $kemarin = now()->subDay();
        $tanggal = ($kemarin->isSunday() ? $kemarin->subDay() : $kemarin)->toDateString();

        $this->post(route('absensi.store', $karyawan), [
            'tanggal' => $tanggal,
            'status' => 'Hadir',
        ])->assertRedirect();

        $absensi = Absensi::where('karyawan_id', $karyawan->id)->whereDate('tanggal', $tanggal)->first();
        expect($absensi)->not->toBeNull();
        expect($absensi->status)->toBe('Hadir');

        $this->post(route('absensi.store', $karyawan), [
            'tanggal' => $tanggal,
            'status' => 'Sakit',
            'catatan' => 'Demam',
        ])->assertRedirect();

        expect(Absensi::where('karyawan_id', $karyawan->id)->whereDate('tanggal', $tanggal)->count())->toBe(1);

        $absensi->refresh();
        expect($absensi->status)->toBe('Sakit');
        expect($absensi->catatan)->toBe('Demam');
    });

    it('accepts Cuti and Dinas Luar Kota as attendance statuses', function () {
        $karyawan = Karyawan::create([
            'nik' => 'EMP-STATUS-BARU',
            'nama_lengkap' => 'Status Baru',
            'tanggal_lahir' => '1990-01-01',
            'unit_kerja_id' => $this->unitKerja->id,
            'jabatan' => 'Staf',
            'status_karyawan' => 'Tetap',
            'gaji_pokok' => 7000000,
        ]);

        foreach (['Cuti', 'Dinas Luar Kota'] as $index => $status) {
            $tanggal = now()->subDays($index + 2);
            if ($tanggal->isSunday()) {
                $tanggal = $tanggal->subDay();
            }

            $this->post(route('absensi.store', $karyawan), [
                'tanggal' => $tanggal->toDateString(),
                'status' => $status,
            ])->assertRedirect();

            expect(Absensi::query()
                ->where('karyawan_id', $karyawan->id)
                ->whereDate('tanggal', $tanggal->toDateString())
                ->where('status', $status)
                ->exists())->toBeTrue();
        }
    });

    it('only allows approved non-working statuses on Sunday', function () {
        $karyawan = Karyawan::create([
            'nik' => 'EMP-001',
            'nama_lengkap' => 'Budi Aktif',
            'tanggal_lahir' => '1990-01-01',
            'unit_kerja_id' => $this->unitKerja->id,
            'jabatan' => 'Staf IT',
            'status_karyawan' => 'Tetap',
            'gaji_pokok' => 7000000,
        ]);

        $hariMinggu = Carbon::now()->startOfWeek(Carbon::SUNDAY)->toDateString();

        $this->post(route('absensi.store', $karyawan), [
            'tanggal' => $hariMinggu,
            'status' => 'Hadir',
        ])->assertSessionHasErrors('absensi');

        expect(Absensi::where('karyawan_id', $karyawan->id)->whereDate('tanggal', $hariMinggu)->exists())->toBeFalse();

        $this->post(route('absensi.store', $karyawan), [
            'tanggal' => $hariMinggu,
            'status' => 'Izin',
        ])->assertRedirect();

        $absensi = Absensi::where('karyawan_id', $karyawan->id)->whereDate('tanggal', $hariMinggu)->first();
        expect($absensi)->not->toBeNull();
        expect($absensi->status)->toBe('Izin');

        $this->post(route('absensi.store', $karyawan), [
            'tanggal' => $hariMinggu,
            'status' => 'Dinas Luar Kota',
        ])->assertRedirect();

        expect($absensi->refresh()->status)->toBe('Dinas Luar Kota');

        $this->post(route('absensi.store', $karyawan), [
            'tanggal' => $hariMinggu,
            'status' => 'Cuti',
        ])->assertSessionHasErrors('absensi');
    });

    it('rejects dates in the future', function () {
        $karyawan = Karyawan::create([
            'nik' => 'EMP-001',
            'nama_lengkap' => 'Budi Aktif',
            'tanggal_lahir' => '1990-01-01',
            'unit_kerja_id' => $this->unitKerja->id,
            'jabatan' => 'Staf IT',
            'status_karyawan' => 'Tetap',
            'gaji_pokok' => 7000000,
        ]);

        $besok = now()->addDay()->toDateString();

        $this->post(route('absensi.store', $karyawan), [
            'tanggal' => $besok,
            'status' => 'Hadir',
        ])->assertSessionHasErrors('tanggal');

        $this->assertDatabaseMissing('absensi', ['karyawan_id' => $karyawan->id, 'tanggal' => $besok]);
    });

    it('still allows filling attendance for karyawan who have already left', function () {
        $karyawan = Karyawan::create([
            'nik' => 'EMP-003',
            'nama_lengkap' => 'Eko Sudah Keluar',
            'tanggal_lahir' => '1991-01-01',
            'unit_kerja_id' => $this->unitKerja->id,
            'jabatan' => 'Staf Umum',
            'status_karyawan' => 'Honorer',
            'tanggal_mengundurkan_diri' => now()->subMonth()->toDateString(),
            'gaji_pokok' => 4800000,
        ]);

        // Mundur 1 hari lagi jika jatuh di hari Minggu: controller hanya
        // mengizinkan status Izin/Sakit untuk tanggal hari Minggu, sedangkan
        // test ini sengaja menguji status Hadir.
        $sebulanLalu = now()->subMonth();
        $tanggal = ($sebulanLalu->isSunday() ? $sebulanLalu->subDay() : $sebulanLalu)->toDateString();

        $this->post(route('absensi.store', $karyawan), [
            'tanggal' => $tanggal,
            'status' => 'Hadir',
        ])->assertRedirect();

        $absensi = Absensi::where('karyawan_id', $karyawan->id)->whereDate('tanggal', $tanggal)->first();
        expect($absensi)->not->toBeNull();
        expect($absensi->status)->toBe('Hadir');
    });
});
