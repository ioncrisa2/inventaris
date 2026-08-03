<?php

namespace Database\Seeders;

use App\Models\Koperasi;
use App\Models\Role;
use App\Models\User;
use App\Services\KoperasiService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

/**
 * Versi ringan MultiPrimerDemoSeeder: hanya akun super_admin + admin_primer
 * untuk 4 koperasi primer yang sama (nama koperasi & email admin identik),
 * TANPA unit kerja, karyawan, inventaris, atau riwayat gaji. Berguna kalau
 * cuma butuh login per-koperasi untuk uji multi-tenant tanpa menunggu
 * seluruh data operasional ikut ter-generate.
 *
 * Berdiri sendiri (tidak dipanggil dari DatabaseSeeder). Jalankan lewat
 * `php artisan db:seed --class=MultiPrimerUserSeeder`.
 */
class MultiPrimerUserSeeder extends Seeder
{
    private const PRIMER = [
        [
            'nama' => 'Koperasi Rukun',
            'admin_nama' => 'Admin Primer Rukun',
            'admin_email' => 'admin.primer.rukun@example.com',
        ],
        [
            'nama' => 'Koperasi Karya Jasa',
            'admin_nama' => 'Admin Primer Karya Jasa',
            'admin_email' => 'admin.primer.karyajasa@example.com',
        ],
        [
            'nama' => 'Koperasi Abdi Sesama',
            'admin_nama' => 'Admin Primer Abdi Sesama',
            'admin_email' => 'admin.primer.abdisesama@example.com',
        ],
        [
            'nama' => 'Koperasi Sentosa',
            'admin_nama' => 'Admin Primer Sentosa',
            'admin_email' => 'admin.primer.sentosa@example.com',
        ],
    ];

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('MultiPrimerUserSeeder berisi akun demo sehingga hanya boleh dijalankan di local atau testing.');
        }

        try {
            DB::transaction(function () {
                $this->call(PermissionSeeder::class);
                $this->seedSuperAdmin();

                foreach (self::PRIMER as $primer) {
                    $this->seedAdminPrimer($primer);
                }
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
        $superAdminRole = Role::query()
            ->where('name', 'super_admin')
            ->where('guard_name', 'web')
            ->whereNull('koperasi_id')
            ->firstOrFail();
        $user->syncRoles([$superAdminRole]);
    }

    /**
     * KoperasiService::store() selalu insert koperasi baru (tidak ada
     * existence check di dalamnya) — guard di sini yang menjaga seeder ini
     * tetap idempotent saat dijalankan ulang.
     */
    private function seedAdminPrimer(array $primer): void
    {
        if (Koperasi::where('nama', $primer['nama'])->exists()) {
            return;
        }

        app(KoperasiService::class)->store([
            'nama' => $primer['nama'],
            'expires_at' => null,
            'admin_nama' => $primer['admin_nama'],
            'admin_email' => $primer['admin_email'],
            'admin_password' => (string) config('demo.user_password'),
        ]);
    }
}
