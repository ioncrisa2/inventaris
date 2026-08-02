<?php

namespace Database\Seeders;

use App\Models\Barang;
use App\Models\Karyawan;
use App\Models\KomponenGaji;
use App\Models\Koperasi;
use App\Models\Role;
use App\Models\TransaksiGaji;
use App\Models\UnitKerja;
use App\Models\User;
use App\Services\KoperasiService;
use App\Services\TransaksiGajiService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeder demo tambahan: 4 koperasi primer sungguhan (bukan "Koperasi Demo"
 * bawaan DatabaseSeeder), masing-masing dengan admin_primer, unit kerja,
 * karyawan, inventaris, dan riwayat transaksi gaji Juli 2024–Juli 2026
 * sendiri-sendiri — dipakai untuk menguji isolasi data multi-tenant dengan
 * volume data yang lebih realistis daripada satu koperasi demo saja.
 *
 * Berdiri sendiri (tidak dipanggil dari DatabaseSeeder) supaya tidak
 * mengubah dataset "Koperasi Demo" yang sudah dikunci angka pastinya oleh
 * DatabaseSeederTest/DatabaseSeederSafetyTest. Bisa dijalankan langsung
 * lewat `php artisan db:seed --class=MultiPrimerDemoSeeder` — seeder ini
 * membuat permission & akun super_admin sendiri kalau belum ada, jadi tidak
 * bergantung pada DatabaseSeeder dijalankan lebih dulu.
 */
class MultiPrimerDemoSeeder extends Seeder
{
    private const PRIMER = [
        [
            'kode' => 'RKN',
            'nama' => 'Koperasi Rukun',
            'admin_nama' => 'Admin Primer Rukun',
            'admin_email' => 'admin.primer.rukun@example.com',
            'jumlah_karyawan' => 6,
            'jumlah_barang' => 18,
        ],
        [
            'kode' => 'KJS',
            'nama' => 'Koperasi Karya Jasa',
            'admin_nama' => 'Admin Primer Karya Jasa',
            'admin_email' => 'admin.primer.karyajasa@example.com',
            'jumlah_karyawan' => 9,
            'jumlah_barang' => 32,
        ],
        [
            'kode' => 'ADS',
            'nama' => 'Koperasi Abdi Sesama',
            'admin_nama' => 'Admin Primer Abdi Sesama',
            'admin_email' => 'admin.primer.abdisesama@example.com',
            'jumlah_karyawan' => 5,
            'jumlah_barang' => 12,
        ],
        [
            'kode' => 'STS',
            'nama' => 'Koperasi Sentosa',
            'admin_nama' => 'Admin Primer Sentosa',
            'admin_email' => 'admin.primer.sentosa@example.com',
            'jumlah_karyawan' => 7,
            'jumlah_barang' => 24,
        ],
    ];

    private const NAMA_DEPAN = [
        'Ahmad', 'Siti', 'Bambang', 'Rina', 'Dedi', 'Yuni', 'Agus', 'Wati',
        'Hendra', 'Lina', 'Fajar', 'Nina', 'Rudi', 'Sri', 'Anton', 'Ayu',
    ];

    private const NAMA_BELAKANG = [
        'Setiawan', 'Handayani', 'Nugraha', 'Puspita', 'Firmansyah', 'Utami',
        'Kurniawan', 'Safitri', 'Gunawan', 'Maharani',
    ];

    private const STATUS_POOL = ['PKWTT', 'PKWTT', 'PKWT', 'Honorer'];

    private const PERIODE_AWAL = '2024-07-01';

    private const PERIODE_AKHIR = '2026-07-01';

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('MultiPrimerDemoSeeder berisi akun dan data demo sehingga hanya boleh dijalankan di local atau testing.');
        }

        try {
            DB::transaction(function () {
                $this->call(PermissionSeeder::class);
                $this->seedSuperAdmin();

                foreach (self::PRIMER as $index => $primer) {
                    $this->seedPrimer($primer, $index);
                }
            }, 3);
        } finally {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }

        $this->pastikanIsolasiAntarPrimer();
    }

    private function seedSuperAdmin(): void
    {
        $password = (string) config('demo.user_password');

        if (blank($password)) {
            throw new \RuntimeException('DEMO_USER_PASSWORD wajib diisi untuk membuat akun demo.');
        }

        $user = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ],
        );
        $user->forceFill([
            'name' => 'Super Admin',
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();
        $superAdminRole = Role::query()
            ->where('name', 'super_admin')
            ->where('guard_name', 'web')
            ->whereNull('koperasi_id')
            ->firstOrFail();
        $user->syncRoles([$superAdminRole]);
    }

    private function seedPrimer(array $primer, int $primerIndex): void
    {
        if (! Koperasi::where('nama', $primer['nama'])->exists()) {
            app(KoperasiService::class)->store([
                'nama' => $primer['nama'],
                'expires_at' => null,
                'admin_nama' => $primer['admin_nama'],
                'admin_email' => $primer['admin_email'],
                'admin_password' => (string) config('demo.user_password'),
            ]);
        }

        // Dari sini, semua model BelongsToKoperasi otomatis ter-tag
        // koperasi_id milik primer ini — persis pola yang sama dengan
        // DatabaseSeeder untuk koperasi demo.
        Auth::setUser(User::where('email', $primer['admin_email'])->firstOrFail());

        $this->call([
            UnitKerjaSeeder::class,
            PengaturanSeeder::class,
            HariLiburSeeder::class,
            KomponenGajiSeeder::class,
        ]);

        $karyawans = $this->seedKaryawan($primer, $primerIndex);
        $this->seedBarang($primer, $primerIndex);
        $this->call(RiwayatKondisiBarangSeeder::class);
        $this->seedTransaksiGaji($karyawans);

        Auth::forgetGuards();
    }

    /**
     * @return Collection<int, Karyawan>
     */
    private function seedKaryawan(array $primer, int $primerIndex): Collection
    {
        $unitKerjaIds = UnitKerja::orderBy('nama_unit')->pluck('id', 'nama_unit');
        $unitNames = $unitKerjaIds->keys()->values();
        $tanggalDasar = CarbonImmutable::create(2019, 1, 1);

        $karyawans = collect();

        for ($i = 0; $i < $primer['jumlah_karyawan']; $i++) {
            $unit = $unitNames[$i % $unitNames->count()];
            $depan = self::NAMA_DEPAN[($primerIndex * 7 + $i) % count(self::NAMA_DEPAN)];
            $belakang = self::NAMA_BELAKANG[($primerIndex * 3 + $i) % count(self::NAMA_BELAKANG)];

            // Sebagian karyawan baru masuk di tengah rentang payroll (bukan
            // semua sejak lama) supaya histori gajinya bervariasi titik
            // mulainya, bukan seragam dari Juli 2024.
            $tanggalMasuk = $i % 4 === 3
                ? CarbonImmutable::create(2025, 3, 1)
                : $tanggalDasar->addMonths($i * 5 + $primerIndex * 2);

            // Sebagian karyawan berhenti di tengah rentang supaya histori
            // gajinya juga bervariasi titik berhentinya.
            $mengundurkanDiri = $i % 6 === 5
                ? CarbonImmutable::create(2025, 6, 30)
                : null;

            $data = [
                'nik' => sprintf('%s-%04d', $primer['kode'], $i + 1),
                'nama_lengkap' => "{$depan} {$belakang}",
                'tempat_lahir' => 'Palembang',
                'tanggal_lahir' => CarbonImmutable::create(
                    1985 + ($i % 15),
                    (($i * 3) % 12) + 1,
                    (($i * 7) % 28) + 1,
                )->toDateString(),
                'jenis_kelamin' => $i % 2 === 0 ? 'Laki-laki' : 'Perempuan',
                'agama' => 'Islam',
                'status_perkawinan' => $i % 3 === 0 ? 'Belum Kawin' : 'Kawin',
                // Prefix per primer membuat data demo mudah dibedakan, walau
                // constraint nomor KTP kini memang ter-scope per koperasi.
                'nomor_ktp' => sprintf('9%02d%013d', $primerIndex + 1, $i + 1),
                'unit_kerja_id' => $unitKerjaIds[$unit],
                'tanggal_masuk_kerja' => $tanggalMasuk->toDateString(),
                'jabatan' => $i < $unitNames->count() ? "Kepala {$unit}" : "Staff {$unit}",
                'status_karyawan' => self::STATUS_POOL[$i % count(self::STATUS_POOL)],
                'nomor_sk_pengangkatan' => sprintf('SK/%s/%03d/%d', $primer['kode'], $i + 1, $tanggalMasuk->year),
                'tanggal_sk_pengangkatan' => $tanggalMasuk->toDateString(),
                'gaji_pokok' => 4200000 + ($i % 8) * 350000 + $primerIndex * 150000,
            ];

            if ($mengundurkanDiri) {
                $data['tanggal_mengundurkan_diri'] = $mengundurkanDiri->toDateString();
            }

            $karyawans->push(Karyawan::updateOrCreate(['nik' => $data['nik']], $data));
        }

        return $karyawans;
    }

    private function seedBarang(array $primer, int $primerIndex): void
    {
        $unitKerjaIds = UnitKerja::orderBy('nama_unit')->pluck('id')->all();
        $katalog = config('kategori_penyusutan.item_per_golongan');
        $golongan = array_keys($katalog);
        $jumlahGolongan = count($golongan);
        $rentangHarga = [
            'Bukan Bangunan - Kelompok 1' => [500000, 25000000],
            'Bukan Bangunan - Kelompok 2' => [2000000, 350000000],
            'Bukan Bangunan - Kelompok 3' => [5000000, 500000000],
            'Bukan Bangunan - Kelompok 4' => [500000000, 5000000000],
            'Bangunan - Permanen' => [250000000, 10000000000],
            'Bangunan - Bukan Permanen' => [25000000, 500000000],
        ];

        $target = $primer['jumlah_barang'];
        $tanggalDasar = CarbonImmutable::create(2021, 3, 1);

        for ($index = 0; $index < $target; $index++) {
            // Titik mulai golongan digeser per primer (primerIndex) supaya
            // komposisi kategori inventaris tiap primer berbeda, bukan
            // sekadar beda jumlah barangnya.
            $golonganIndex = ($index + $primerIndex * 2) % $jumlahGolongan;
            $kategori = $golongan[$golonganIndex];
            $urutanDalamGolongan = intdiv($index, $jumlahGolongan);
            $jenisBarang = $katalog[$kategori][($urutanDalamGolongan + $primerIndex) % count($katalog[$kategori])];
            [$hargaMin, $hargaMax] = $rentangHarga[$kategori];
            $unitKerjaId = $unitKerjaIds[$index % count($unitKerjaIds)];
            $tanggalPerolehan = $tanggalDasar->addDays($index * 17 + $primerIndex * 45)->toDateString();
            $langkah = max(1, intdiv($hargaMax - $hargaMin, 50000));
            $harga = $hargaMin + (($index * 13 + $primerIndex * 29) % ($langkah + 1)) * 50000;

            Barang::updateOrCreate(
                ['kode_barang' => sprintf('%s-BRG-%03d', $primer['kode'], $index + 1)],
                [
                    'nama_barang' => $jenisBarang.' '.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT),
                    'kategori' => $kategori,
                    'jenis_barang' => $jenisBarang,
                    'unit_kerja_id' => $unitKerjaId,
                    'tanggal_perolehan' => $tanggalPerolehan,
                    'harga_perolehan' => $harga,
                ],
            );
        }
    }

    /**
     * @param  Collection<int, Karyawan>  $karyawans
     */
    private function seedTransaksiGaji(Collection $karyawans): void
    {
        $service = app(TransaksiGajiService::class);
        $komponenList = KomponenGaji::orderBy('id')->get();
        $periodeAwal = CarbonImmutable::parse(self::PERIODE_AWAL);
        $periodeAkhir = CarbonImmutable::parse(self::PERIODE_AKHIR);

        foreach ($karyawans as $karyawan) {
            $mulaiKerja = CarbonImmutable::parse($karyawan->tanggal_masuk_kerja)->startOfMonth();
            $berhentiKerja = $karyawan->tanggal_mengundurkan_diri
                ? CarbonImmutable::parse($karyawan->tanggal_mengundurkan_diri)
                : null;

            for ($periode = $periodeAwal; $periode->lte($periodeAkhir); $periode = $periode->addMonth()) {
                if ($periode->lt($mulaiKerja)) {
                    continue;
                }

                if ($berhentiKerja && $periode->startOfMonth()->gt($berhentiKerja)) {
                    continue;
                }

                $baris = $komponenList->mapWithKeys(function (KomponenGaji $komponen) use ($periode) {
                    $row = ['pakai' => '1'];

                    if ($komponen->metode_perhitungan === 'per_hari') {
                        $row['tanggal_awal'] = $periode->startOfMonth()->toDateString();
                        $row['tanggal_akhir'] = $periode->endOfMonth()->toDateString();
                    }

                    return ["master_{$komponen->id}" => $row];
                })->all();

                $header = [
                    'karyawan_id' => $karyawan->id,
                    'bulan' => $periode->month,
                    'tahun' => $periode->year,
                ];

                $transaksi = TransaksiGaji::query()->where($header)->first();

                $transaksi
                    ? $service->update($transaksi, $header, $baris)
                    : $service->store($header, $baris);
            }
        }
    }

    /**
     * Verifikasi runtime bahwa data antar primer tidak bocor: setiap admin
     * primer benar terhubung ke koperasinya sendiri, dan ketika login
     * sebagai admin primer tertentu, karyawan primer LAIN tidak ikut
     * terlihat lewat KoperasiScope. Dijalankan di luar transaction utama
     * (butuh koneksi sudah commit) dan tanpa user aktif dulu supaya query
     * awal (hitung karyawan per koperasi_id) tidak ikut ter-scope.
     */
    private function pastikanIsolasiAntarPrimer(): void
    {
        Auth::forgetGuards();

        $koperasiIds = Koperasi::whereIn('nama', array_column(self::PRIMER, 'nama'))->pluck('id', 'nama');

        foreach (self::PRIMER as $primer) {
            $koperasiId = $koperasiIds[$primer['nama']] ?? null;

            if ($koperasiId === null) {
                throw new \RuntimeException("Koperasi {$primer['nama']} gagal dibuat.");
            }

            $admin = User::where('email', $primer['admin_email'])->firstOrFail();

            if ((int) $admin->koperasi_id !== (int) $koperasiId) {
                throw new \RuntimeException("Admin primer {$primer['admin_email']} tidak terhubung ke koperasi {$primer['nama']}.");
            }

            $karyawanSalahTertaut = Karyawan::where('koperasi_id', $koperasiId)
                ->where('nik', 'not like', $primer['kode'].'-%')
                ->count();

            if ($karyawanSalahTertaut > 0) {
                throw new \RuntimeException("Ditemukan karyawan primer lain yang salah tertaut ke koperasi {$primer['nama']}.");
            }

            $barangSalahTertaut = Barang::where('koperasi_id', $koperasiId)
                ->where('kode_barang', 'not like', $primer['kode'].'-%')
                ->count();

            if ($barangSalahTertaut > 0) {
                throw new \RuntimeException("Ditemukan barang primer lain yang salah tertaut ke koperasi {$primer['nama']}.");
            }

            // Login sebagai admin primer ini: KoperasiScope harus memfilter
            // habis karyawan & barang primer lain dari hasil query.
            Auth::setUser($admin);
            $nikTerlihat = Karyawan::pluck('nik');
            $kodeBarangTerlihat = Barang::pluck('kode_barang');
            Auth::forgetGuards();

            foreach (self::PRIMER as $lain) {
                if ($lain['kode'] === $primer['kode']) {
                    continue;
                }

                if ($nikTerlihat->contains(fn ($nik) => str_starts_with($nik, $lain['kode'].'-'))) {
                    throw new \RuntimeException("Kebocoran data: admin primer {$primer['nama']} bisa melihat karyawan primer {$lain['nama']}.");
                }

                if ($kodeBarangTerlihat->contains(fn ($kode) => str_starts_with($kode, $lain['kode'].'-'))) {
                    throw new \RuntimeException("Kebocoran data: admin primer {$primer['nama']} bisa melihat barang primer {$lain['nama']}.");
                }
            }
        }
    }
}
