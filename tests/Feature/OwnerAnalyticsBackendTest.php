<?php

use App\Services\OwnerAnalyticsService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo('2026-08-28 12:00:00');
    Cache::flush();
});

afterEach(function () {
    $this->travelBack();
});

it('menghasilkan agregat global dan per koperasi tanpa model atau data personal', function () {
    $koperasiA = ownerAnalyticsCreateKoperasi('Koperasi Analitik A');
    $koperasiB = ownerAnalyticsCreateKoperasi('Koperasi Analitik B');
    $unitA = ownerAnalyticsCreateUnit($koperasiA, 'Operasional A');
    $unitB = ownerAnalyticsCreateUnit($koperasiB, 'Operasional B');

    DB::table('users')->insert([
        ownerAnalyticsUserRow($koperasiA, 'Nama User Rahasia A', 'owner-a-secret@example.test'),
        ownerAnalyticsUserRow($koperasiB, 'Nama User Rahasia B', 'owner-b-secret@example.test'),
    ]);

    $employeeIds = [];
    foreach (range(1, 5) as $number) {
        $employeeIds[] = ownerAnalyticsCreateEmployee(
            $koperasiA,
            $unitA,
            'Pegawai Rahasia A '.$number,
            'NIK-A-'.$number,
        );
    }
    foreach (range(1, 4) as $number) {
        ownerAnalyticsCreateEmployee(
            $koperasiB,
            $unitB,
            'Pegawai Rahasia B '.$number,
            'NIK-B-'.$number,
        );
    }

    $barangA1 = ownerAnalyticsCreateInventory($koperasiA, $unitA, 'Kode-Rahasia-A1', 'Laptop Rahasia A1', '1000000.00');
    ownerAnalyticsCreateInventory($koperasiA, $unitA, 'Kode-Rahasia-A2', 'Laptop Rahasia A2', '2000000.00');
    ownerAnalyticsCreateInventory($koperasiB, $unitB, 'Kode-Rahasia-B1', 'Laptop Rahasia B1', '9000000.00');
    DB::table('riwayat_kondisi_barang')->insert([
        'barang_id' => $barangA1,
        'tanggal_pemeriksaan' => '2026-08-01',
        'kondisi' => 'Baik',
        'keterangan' => 'Catatan rahasia kondisi',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    foreach ($employeeIds as $index => $employeeId) {
        DB::table('absensi')->insert([
            'koperasi_id' => $koperasiA,
            'karyawan_id' => $employeeId,
            'tanggal' => sprintf('2026-08-%02d', $index + 1),
            'status' => 'Hadir',
            'catatan' => 'Catatan absensi rahasia',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        ownerAnalyticsCreatePayroll($koperasiA, $employeeId, '1000000.00', '1200000.00');
    }

    $service = app(OwnerAnalyticsService::class);
    $filters = [
        'tanggal_awal' => '2026-01-01',
        'tanggal_akhir' => '2026-08-28',
        'modul' => 'semua',
    ];
    $global = $service->analytics($filters);
    $tenant = $service->koperasi($koperasiA, $filters);

    expect($global['platform']['koperasi']['total'])->toBe(2)
        ->and($global['platform']['inventaris']['total_barang'])->toBe(3)
        ->and($global['platform']['inventaris']['total_harga_perolehan'])->toBe('12000000.00')
        ->and($tenant['koperasi'])->toBe(['id' => $koperasiA, 'nama' => 'Koperasi Analitik A'])
        ->and($tenant['inventaris']['ringkasan']['total_barang'])->toBe(2)
        ->and($tenant['inventaris']['ringkasan']['total_harga_perolehan'])->toBe('3000000.00')
        ->and($tenant['kepegawaian']['ringkasan']['aktif'])->toBe(5)
        ->and($tenant['absensi']['ringkasan']['persentase_hadir']['nilai'])->toBe('100.0')
        ->and($tenant['penggajian']['ringkasan']['total_gaji_bersih'])->toBe('6000000.00')
        ->and($tenant['penggajian']['ringkasan']['rata_rata_gaji_bersih']['nilai'])->toBe('1200000.00');

    assertOwnerAnalyticsContainsNoModel($global);
    assertOwnerAnalyticsContainsNoModel($tenant);

    $json = json_encode([$global, $tenant], JSON_THROW_ON_ERROR);
    expect($json)
        ->not->toContain('Nama User Rahasia')
        ->not->toContain('owner-a-secret@example.test')
        ->not->toContain('Pegawai Rahasia')
        ->not->toContain('NIK-A-')
        ->not->toContain('Kode-Rahasia')
        ->not->toContain('Laptop Rahasia')
        ->not->toContain('Catatan rahasia')
        ->not->toContain('barang_id')
        ->not->toContain('karyawan_id')
        ->not->toContain('transaksi_gaji_id');
});

it('menyembunyikan turunan penggajian dan absensi untuk cohort kecil', function () {
    $koperasiId = ownerAnalyticsCreateKoperasi('Koperasi Cohort Kecil');
    $unitId = ownerAnalyticsCreateUnit($koperasiId, 'Unit Sangat Kecil dan Rahasia');

    foreach (range(1, 4) as $number) {
        $employeeId = ownerAnalyticsCreateEmployee(
            $koperasiId,
            $unitId,
            'Identitas Kecil '.$number,
            'SMALL-'.$number,
        );
        DB::table('absensi')->insert([
            'koperasi_id' => $koperasiId,
            'karyawan_id' => $employeeId,
            'tanggal' => sprintf('2026-08-%02d', $number),
            'status' => $number === 1 ? 'Sakit' : 'Hadir',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        ownerAnalyticsCreatePayroll($koperasiId, $employeeId, '1000000.00', (string) (1000000 + $number));
    }

    $result = app(OwnerAnalyticsService::class)->koperasi($koperasiId, [
        'tanggal_awal' => '2026-08-01',
        'tanggal_akhir' => '2026-08-28',
        'modul' => 'semua',
    ]);

    expect($result['penggajian']['ringkasan']['total_gaji_bersih'])->toBe('4000010.00')
        ->and($result['penggajian']['ringkasan']['rata_rata_gaji_bersih'])->toMatchArray([
            'nilai' => null,
            'disembunyikan' => true,
        ])->and($result['absensi']['ringkasan']['persentase_hadir'])->toMatchArray([
            'nilai' => null,
            'disembunyikan' => true,
        ])->and($result['absensi']['grafik']['series'][0]['data'])->each->toBeNull()
        ->and(json_encode($result['kepegawaian']['distribusi'], JSON_THROW_ON_ERROR))
        ->not->toContain('Unit Sangat Kecil dan Rahasia');
});

it('memisahkan cache berdasarkan scope periode koperasi dan modul', function () {
    $koperasiA = ownerAnalyticsCreateKoperasi('Cache A');
    $koperasiB = ownerAnalyticsCreateKoperasi('Cache B');
    $unitA = ownerAnalyticsCreateUnit($koperasiA, 'Unit Cache A');
    $unitB = ownerAnalyticsCreateUnit($koperasiB, 'Unit Cache B');
    ownerAnalyticsCreateInventory($koperasiA, $unitA, 'CACHE-A-1', 'Cache A 1', '100.00');
    ownerAnalyticsCreateInventory($koperasiA, $unitA, 'CACHE-A-2', 'Cache A 2', '200.00');
    ownerAnalyticsCreateInventory($koperasiB, $unitB, 'CACHE-B-1', 'Cache B 1', '900.00');

    $service = app(OwnerAnalyticsService::class);
    $base = [
        'tanggal_awal' => '2026-01-01',
        'tanggal_akhir' => '2026-08-28',
        'modul' => 'inventaris',
    ];
    $filtersA = [...$base, 'koperasi_id' => $koperasiA];
    $filtersB = [...$base, 'koperasi_id' => $koperasiB];
    $resultA = $service->koperasi($koperasiA, $filtersA);
    $resultB = $service->koperasi($koperasiB, $filtersB);

    expect($resultA['inventaris']['ringkasan']['total_barang'])->toBe(2)
        ->and($resultB['inventaris']['ringkasan']['total_barang'])->toBe(1)
        ->and($service->cacheKey('analytics-koperasi-'.$koperasiA, $filtersA))
        ->not->toBe($service->cacheKey('analytics-koperasi-'.$koperasiB, $filtersB))
        ->and(Cache::has($service->cacheKey('analytics-koperasi-'.$koperasiA, $filtersA)))->toBeTrue()
        ->and(Cache::has($service->cacheKey('analytics-koperasi-'.$koperasiB, $filtersB)))->toBeTrue();
});

it('menolak rentang liar modul tidak dikenal dan filter koperasi yang tidak ada', function () {
    $this->actingAs(systemOwnerUser());

    $this->from(route('owner.analytics'))
        ->get(route('owner.analytics', [
            'tanggal_awal' => '2023-01-01',
            'tanggal_akhir' => '2026-08-28',
            'modul' => 'data-personal',
            'koperasi_id' => 999999,
        ]))
        ->assertRedirect(route('owner.analytics'))
        ->assertSessionHasErrors(['tanggal_awal', 'modul', 'koperasi_id']);
});

it('merender dashboard analitik global dan ringkasan koperasi untuk owner', function () {
    $owner = systemOwnerUser();
    $koperasiId = ownerAnalyticsCreateKoperasi('Koperasi Tampilan Agregat');

    $this->actingAs($owner)
        ->get(route('owner.dashboard'))
        ->assertOk()
        ->assertSee('Ringkasan Platform')
        ->assertDontSee('nama_lengkap');

    $this->get(route('owner.analytics'))
        ->assertOk()
        ->assertSee('Analitik Koperasi');

    $this->get(route('owner.analytics.koperasi', ['koperasi' => $koperasiId]))
        ->assertOk()
        ->assertSee('Koperasi Tampilan Agregat')
        ->assertDontSee('Data operasional');
});

function ownerAnalyticsCreateKoperasi(string $name): int
{
    return DB::table('koperasi')->insertGetId([
        'nama' => $name,
        'is_active' => true,
        'expires_at' => '2027-12-31 23:59:59',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function ownerAnalyticsCreateUnit(int $koperasiId, string $name): int
{
    return DB::table('unit_kerja')->insertGetId([
        'koperasi_id' => $koperasiId,
        'nama_unit' => $name,
        'kode' => 'U'.Str::upper(Str::random(5)),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function ownerAnalyticsUserRow(int $koperasiId, string $name, string $email): array
{
    return [
        'koperasi_id' => $koperasiId,
        'unit_kerja_id' => null,
        'name' => $name,
        'email' => $email,
        'email_verified_at' => now(),
        'password' => bcrypt('password'),
        'created_at' => '2026-02-01 00:00:00',
        'updated_at' => now(),
    ];
}

function ownerAnalyticsCreateEmployee(int $koperasiId, int $unitId, string $name, string $nik): int
{
    return DB::table('karyawan')->insertGetId([
        'koperasi_id' => $koperasiId,
        'nik' => $nik,
        'nama_lengkap' => $name,
        'tanggal_lahir' => '1990-01-01',
        'unit_kerja_id' => $unitId,
        'tanggal_masuk_kerja' => '2026-02-01',
        'jabatan' => 'Staf',
        'status_karyawan' => 'PKWTT',
        'gaji_pokok' => '1000000.00',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function ownerAnalyticsCreateInventory(int $koperasiId, int $unitId, string $code, string $name, string $price): int
{
    return DB::table('barang')->insertGetId([
        'koperasi_id' => $koperasiId,
        'kode_barang' => $code,
        'nama_barang' => $name,
        'kategori' => 'Bukan Bangunan - Kelompok 1',
        'jenis_barang' => 'Laptop / notebook',
        'unit_kerja_id' => $unitId,
        'lokasi_penempatan' => 'Kantor Pusat',
        'tanggal_perolehan' => '2026-02-01',
        'harga_perolehan' => $price,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function ownerAnalyticsCreatePayroll(int $koperasiId, int $employeeId, string $base, string $net): void
{
    $payrollId = DB::table('transaksi_gaji')->insertGetId([
        'koperasi_id' => $koperasiId,
        'karyawan_id' => $employeeId,
        'bulan' => 8,
        'tahun' => 2026,
        'gaji_pokok' => $base,
        'gaji_bersih' => $net,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('transaksi_gaji_detail')->insert([
        'transaksi_gaji_id' => $payrollId,
        'komponen_gaji_id' => null,
        'nama_komponen_snapshot' => 'Tunjangan agregat',
        'jenis_snapshot' => 'Tunjangan',
        'metode_perhitungan_snapshot' => 'nominal_tetap',
        'nilai_snapshot' => '200000.00',
        'nominal_hasil' => '200000.00',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function assertOwnerAnalyticsContainsNoModel(mixed $value): void
{
    if ($value instanceof Model) {
        test()->fail('Payload analitik owner tidak boleh membawa model Eloquent.');
    }

    if (! is_array($value)) {
        return;
    }

    foreach ($value as $nested) {
        assertOwnerAnalyticsContainsNoModel($nested);
    }
}
