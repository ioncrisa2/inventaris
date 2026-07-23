<?php

use App\Models\Absensi;
use App\Models\Barang;
use App\Models\DokumenBarang;
use App\Models\Karyawan;
use App\Models\RiwayatKondisiBarang;
use App\Models\UnitKerja;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

test('aplikasi menggunakan zona waktu dan locale Indonesia', function () {
    expect(config('app.timezone'))->toBe('Asia/Jakarta')
        ->and(config('app.locale'))->toBe('id')
        ->and(date_default_timezone_get())->toBe('Asia/Jakarta')
        ->and(now()->month(7)->translatedFormat('F'))->toBe('Juli');
});

test('periode dashboard berganti tepat pada tengah malam tanggal 25 waktu Jakarta', function () {
    $this->travelTo(CarbonImmutable::parse('2026-07-25 00:30:00', 'Asia/Jakarta'));

    $this->actingAs(adminUser())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertViewHas('trenAbsensi', fn (array $data) => $data['periode'] === '25 Jul 2026 – 24 Agt 2026');
});

test('dashboard displays payroll attendance inventory condition and data quality widgets', function () {
    $this->travelTo('2026-07-24 09:00:00');
    $this->actingAs(adminUser());

    $unitKerja = UnitKerja::create(['nama_unit' => 'IT']);

    $barangBaik = Barang::create([
        'kode_barang' => 'INV-001',
        'nama_barang' => 'Laptop Operasional',
        'kategori' => 'Bukan Bangunan - Kelompok 1',
        'unit_kerja_id' => $unitKerja->id,
        'tanggal_perolehan' => now()->startOfYear()->addMonth(),
        'harga_perolehan' => 2000000,
    ]);

    $barangRusak = Barang::create([
        'kode_barang' => 'INV-002',
        'nama_barang' => 'Kursi Kantor',
        'kategori' => 'Bukan Bangunan - Kelompok 2',
        'unit_kerja_id' => $unitKerja->id,
        'tanggal_perolehan' => now()->startOfYear()->addMonths(2),
        'harga_perolehan' => 1000000,
    ]);

    RiwayatKondisiBarang::create([
        'barang_id' => $barangBaik->id,
        'tanggal_pemeriksaan' => now(),
        'kondisi' => 'Baik',
    ]);

    RiwayatKondisiBarang::create([
        'barang_id' => $barangRusak->id,
        'tanggal_pemeriksaan' => now(),
        'kondisi' => 'Rusak Ringan',
    ]);

    $barangBaik->update(['foto_sampul' => 'barang-sampul/laptop.jpg']);
    DokumenBarang::create([
        'barang_id' => $barangBaik->id,
        'jenis_dokumen' => 'Nota Pembelian',
        'nama_asli' => 'nota-laptop.pdf',
        'path' => 'dokumen-barang/nota-laptop.pdf',
    ]);

    $budi = Karyawan::create([
        'nik' => 'EMP-001',
        'nama_lengkap' => 'Budi Aktif',
        'tanggal_lahir' => '1990-01-01',
        'unit_kerja_id' => $unitKerja->id,
        'jabatan' => 'Staf IT',
        'status_karyawan' => 'Tetap',
        'gaji_pokok' => 7000000,
    ]);

    $sari = Karyawan::create([
        'nik' => 'EMP-002',
        'nama_lengkap' => 'Sari Honorer',
        'tanggal_lahir' => '1992-01-01',
        'unit_kerja_id' => $unitKerja->id,
        'jabatan' => 'Analis',
        'status_karyawan' => 'Honorer',
        'tanggal_mengundurkan_diri' => now()->subMonth()->toDateString(),
        'gaji_pokok' => 6000000,
    ]);

    Absensi::create(['karyawan_id' => $budi->id, 'tanggal' => '2026-06-25', 'status' => 'Hadir']);
    Absensi::create(['karyawan_id' => $budi->id, 'tanggal' => '2026-07-01', 'status' => 'Izin']);
    Absensi::create(['karyawan_id' => $budi->id, 'tanggal' => '2026-07-20', 'status' => 'Alpha']);
    Absensi::create(['karyawan_id' => $budi->id, 'tanggal' => '2026-07-24', 'status' => 'Hadir']);
    Absensi::create(['karyawan_id' => $budi->id, 'tanggal' => '2026-07-25', 'status' => 'Hadir']);
    Absensi::create(['karyawan_id' => $sari->id, 'tanggal' => '2026-07-02', 'status' => 'Sakit']);
    Absensi::create(['karyawan_id' => $budi->id, 'tanggal' => '2026-05-25', 'status' => 'Hadir']);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Tren Absensi Periode Penggajian')
        ->assertSee('data-previous-period-toggle', false)
        ->assertSee('data-weekends=', false)
        ->assertSee('condition-stack', false)
        ->assertDontSee('chartKondisiInventaris', false)
        ->assertSee('25 Jun 2026 – 24 Jul 2026')
        ->assertSee('Kondisi Inventaris')
        ->assertSee('Data Belum Lengkap')
        ->assertSee('Rp 3.000.000')
        ->assertViewHas('totalBarang', 2)
        ->assertViewHas('totalNilaiInventaris', 3000000)
        ->assertViewHas('barangPerluPerbaikan', 1)
        ->assertViewHas('karyawanAktif', 1)
        ->assertViewHas('trenAbsensi', function (array $data) {
            return $data['statusPeriode'] === 'Berjalan'
                && $data['ringkasan'] === [
                    'Hadir' => 2,
                    'Izin' => 1,
                    'Sakit' => 1,
                    'Cuti' => 0,
                    'Dinas Luar Kota' => 0,
                    'Alpha' => 1,
                ]
                && count($data['akhirPekan']) === count($data['labels'])
                && ! str_starts_with($data['labels'][0], 'Hari ')
                && array_sum(array_filter($data['hadirSebelumnya'], fn ($nilai) => $nilai !== null)) === 1;
        })
        ->assertViewHas('kondisiInventaris', function (array $data) {
            return $data['layak']['total'] === 1
                && $data['perlu-perhatian']['total'] === 1
                && $data['bermasalah']['total'] === 0;
        })
        ->assertViewHas('dataBelumLengkap', function (array $data) {
            return $data === [
                'barangBelumDiperiksa' => 0,
                'barangTanpaFoto' => 1,
                'barangTanpaNota' => 1,
                'karyawanTidakLengkap' => 1,
            ];
        })
        ->assertSee(route('barang.index', ['kelengkapan' => 'tanpa-foto']), false)
        ->assertSee(route('karyawan.index', ['kelengkapan' => 'data-inti']), false);
});

test('dashboard suppresses empty condition and completeness cards', function () {
    $this->actingAs(adminUser())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Belum ada data kondisi inventaris.')
        ->assertSee('Semua data prioritas sudah lengkap.')
        ->assertDontSee('chartKondisiInventaris', false);
});

test('dashboard only shows widgets the role has permission for', function () {
    Barang::create([
        'kode_barang' => 'INV-001',
        'nama_barang' => 'Laptop Operasional',
        'kategori' => 'Bukan Bangunan - Kelompok 1',
        'unit_kerja_id' => UnitKerja::create(['nama_unit' => 'IT'])->id,
        'tanggal_perolehan' => now(),
        'harga_perolehan' => 2000000,
    ]);

    // staffUser() menjalankan PermissionSeeder dulu, supaya permission-nya
    // sudah ada di database sebelum dipakai oleh role kustom di bawah ini.
    $user = staffUser(['email' => 'terbatas@example.com']);

    // Role kustom: hanya izin untuk kartu Total Inventaris, tanpa widget lain.
    $role = Role::findOrCreate('Hanya Total Inventaris', 'web');
    $role->syncPermissions(['dashboard.total-inventaris.view']);
    $user->syncRoles(['Hanya Total Inventaris']);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertViewHas('totalBarang', 1);
    $response->assertViewHas('totalNilaiInventaris', fn ($value) => is_null($value));
    $response->assertViewHas('karyawanAktif', fn ($value) => is_null($value));
    $response->assertViewHas('barangPerluPerbaikan', fn ($value) => is_null($value));
    $response->assertViewHas('trenAbsensi', fn ($value) => is_null($value));
    $response->assertViewHas('kondisiInventaris', []);
    $response->assertViewHas('dataBelumLengkap', []);
    $response->assertDontSee('Nilai Aset');
    $response->assertDontSee('Karyawan Aktif');
    $response->assertDontSee('Tren Absensi Periode Penggajian');
});

test('dashboard attendance navigation uses an explicit 25 to 24 payroll period', function () {
    $this->travelTo('2026-07-21 09:00:00');

    $this->actingAs(adminUser())
        ->get(route('dashboard', ['periode' => '2026-05-25']))
        ->assertOk()
        ->assertViewHas('trenAbsensi', function (array $data) {
            return $data['periode'] === '25 Mei 2026 – 24 Jun 2026'
                && $data['statusPeriode'] === 'Selesai'
                && $data['periodeSebelumnyaQuery'] === '2026-04-25'
                && $data['periodeBerikutnyaQuery'] === '2026-06-25';
        });
});

test('dashboard shows an empty state when the role has no dashboard widget permission at all', function () {
    $role = Role::findOrCreate('Tanpa Widget Dashboard', 'web');
    $role->syncPermissions([]);
    $user = staffUser(['email' => 'kosong@example.com']);
    $user->syncRoles(['Tanpa Widget Dashboard']);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Belum ada widget dashboard yang bisa ditampilkan');
});
