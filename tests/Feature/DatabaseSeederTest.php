<?php

use App\Models\Absensi;
use App\Models\Barang;
use App\Models\DokumenBarang;
use App\Models\DokumenKaryawan;
use App\Models\FotoBarang;
use App\Models\Karyawan;
use App\Models\KomponenGaji;
use App\Models\TransaksiGajiDetail;
use App\Models\UnitKerja;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\Support\SeederDataset;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo('2026-07-21 12:00:00');
    Storage::fake('public');
    Storage::fake('local');
});

test('database seeder creates a complete usable demo dataset', function () {
    $this->seed(DatabaseSeeder::class);

    expect(SeederDataset::counts())->toBe([
        'permissions' => 43,
        'roles' => 2,
        'users' => 7,
        'units' => 6,
        'employees' => 15,
        'attendance' => 2028,
        'assets' => 50,
        'conditions' => 50,
        'employee_documents' => 20,
        'supporting_photos' => 25,
        'asset_documents' => 54,
        'salary_components' => 7,
        'payrolls' => 39,
        'payroll_details' => 273,
        'settings' => 2,
    ]);

    $admin = User::where('email', 'admin@example.com')->firstOrFail();
    $staff = User::where('email', 'staff@example.com')->firstOrFail();
    expect(Hash::check((string) config('demo.user_password'), $admin->password))->toBeTrue()
        ->and($admin->hasRole('Admin'))->toBeTrue()
        ->and($staff->hasRole('Staff'))->toBeTrue()
        ->and($admin->getAllPermissions())->toHaveCount(43)
        ->and($staff->getAllPermissions())->toHaveCount(22)
        ->and(UnitKerja::whereNull('kode')->count())->toBe(0)
        ->and(Karyawan::whereNotNull('atasan_langsung_id')->count())->toBe(9)
        ->and(Karyawan::whereNotNull('foto_karyawan')->count())->toBe(12)
        ->and(Barang::whereNotNull('foto_sampul')->count())->toBe(40)
        ->and(Absensi::where('status', 'Cuti')->exists())->toBeTrue()
        ->and(Absensi::where('status', 'Dinas Luar Kota')->exists())->toBeTrue();

    expect(TransaksiGajiDetail::where('metode_perhitungan_snapshot', 'per_kehadiran')->count())->toBe(39)
        ->and(TransaksiGajiDetail::where('metode_perhitungan_snapshot', 'per_kehadiran')->whereNull('jumlah_hadir_snapshot')->count())->toBe(0)
        ->and(TransaksiGajiDetail::where('metode_perhitungan_snapshot', 'per_kehadiran')->where('nominal_hasil', '>', 0)->count())->toBe(39);

    if (DB::getDriverName() === 'sqlite') {
        expect(DB::select('PRAGMA foreign_key_check'))->toBe([]);
    }

    expect(TransaksiGajiDetail::whereHas('transaksiGaji', fn ($query) => $query->where('bulan', 7)->where('tahun', 2026))->count())->toBe(0)
        ->and(TransaksiGajiDetail::whereHas('transaksiGaji', fn ($query) => $query->where('bulan', 6)->where('tahun', 2026))->count())->toBe(91);

    Karyawan::whereNotNull('foto_karyawan')->each(
        fn (Karyawan $karyawan) => Storage::disk('public')->assertExists($karyawan->foto_karyawan)
    );
    Barang::whereNotNull('foto_sampul')->each(
        fn (Barang $barang) => Storage::disk('public')->assertExists($barang->foto_sampul)
    );
    DokumenKaryawan::each(fn ($dokumen) => Storage::disk('local')->assertExists($dokumen->path));
    DokumenBarang::each(fn ($dokumen) => Storage::disk('local')->assertExists($dokumen->path));
    FotoBarang::each(fn ($foto) => Storage::disk('public')->assertExists($foto->path));

    $this->actingAs($admin)->get(route('dashboard'))->assertOk();
});

test('database seeder is idempotent and preserves existing demo passwords', function () {
    $this->seed(DatabaseSeeder::class);
    $counts = SeederDataset::counts();
    $password = User::where('email', 'admin@example.com')->value('password');

    $this->seed(DatabaseSeeder::class);

    expect(SeederDataset::counts())->toBe($counts)
        ->and(User::where('email', 'admin@example.com')->value('password'))->toBe($password)
        ->and(KomponenGaji::count())->toBe(KomponenGaji::distinct('nama_komponen')->count('nama_komponen'));

    if (DB::getDriverName() === 'sqlite') {
        expect(DB::select('PRAGMA foreign_key_check'))->toBe([]);
    }
});
