<?php

namespace Database\Seeders;

use App\Models\Koperasi;
use App\Models\User;
use App\Services\KoperasiService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('DatabaseSeeder berisi akun dan data demo sehingga hanya boleh dijalankan di local atau testing.');
        }

        try {
            DB::transaction(function () {
                $this->call(PermissionSeeder::class);

                // super_admin tidak terikat koperasi manapun — dibuat SEBELUM
                // ada koperasi/aktor manapun, jadi koperasi_id-nya tetap null.
                $this->seedSuperAdmin();

                // Koperasi demo + akun admin_primer pertamanya, lewat alur yang
                // sama persis dengan yang dipakai super_admin sungguhan di UI
                // (KoperasiService::store()), supaya data demo merepresentasikan
                // pemakaian nyata, bukan jalan pintas. Dicek dulu supaya seeder
                // ini tetap idempotent — KoperasiService::store() sendiri selalu
                // insert baru (tidak ada existence check), dan re-run tanpa
                // guard ini akan gagal kena unique constraint email admin_primer.
                if (! Koperasi::where('nama', 'Koperasi Demo')->exists()) {
                    app(KoperasiService::class)->store([
                        'nama' => 'Koperasi Demo',
                        'expires_at' => null,
                        'admin_nama' => 'Admin Primer Demo',
                        'admin_email' => 'admin.primer@example.com',
                        'admin_password' => (string) config('demo.user_password'),
                    ]);
                }

                // Dari titik ini, semua model yang pakai trait BelongsToKoperasi
                // otomatis ter-tag koperasi_id milik koperasi demo di atas —
                // seeder-seeder di bawah TIDAK perlu tahu soal koperasi_id sama
                // sekali, sama seperti kalau admin_primer ini benar-benar login
                // dan mengisi data lewat UI.
                Auth::setUser(User::where('email', 'admin.primer@example.com')->firstOrFail());

                $this->call([
                    DemoStaffRoleSeeder::class,
                    UnitKerjaSeeder::class,
                    PengaturanSeeder::class,
                    HariLiburSeeder::class,
                    UserSeeder::class,
                    KaryawanSeeder::class,
                    AbsensiSeeder::class,
                    BarangSeeder::class,
                    RiwayatKondisiBarangSeeder::class,
                    KaryawanMediaSeeder::class,
                    BarangMediaSeeder::class,
                    KomponenGajiSeeder::class,
                    TransaksiGajiSeeder::class,
                ]);

                Auth::forgetGuards();
            }, 3);
        } finally {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
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
        $user->syncRoles(['super_admin']);
    }
}
