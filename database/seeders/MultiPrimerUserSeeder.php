<?php

namespace Database\Seeders;

use App\Models\Koperasi;
use App\Models\Role;
use App\Models\User;
use App\Services\KoperasiService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

/**
 * Versi ringan MultiPrimerDemoSeeder: akun super_admin tetap satu dan global,
 * lalu dibuat 4 koperasi primer dengan 3 user per koperasi (1 admin_primer +
 * 2 Staff). Seeder ini tidak membuat unit kerja, karyawan, inventaris, atau
 * riwayat gaji sehingga cocok untuk uji akun dan isolasi tenant dengan cepat.
 *
 * Dipakai oleh DatabaseSeeder dan tetap bisa dijalankan langsung lewat
 * `php artisan db:seed --class=MultiPrimerUserSeeder`.
 */
class MultiPrimerUserSeeder extends Seeder
{
    private const PRIMER = [
        [
            'nama' => 'Koperasi Rukun',
            'admin_nama' => 'Admin Primer Rukun',
            'admin_email' => 'admin.primer.rukun@example.com',
            'staff' => [
                ['name' => 'Staff Keuangan Rukun', 'email' => 'staff.keuangan.rukun@example.com'],
                ['name' => 'Staff Operasional Rukun', 'email' => 'staff.operasional.rukun@example.com'],
            ],
        ],
        [
            'nama' => 'Koperasi Karya Jasa',
            'admin_nama' => 'Admin Primer Karya Jasa',
            'admin_email' => 'admin.primer.karyajasa@example.com',
            'staff' => [
                ['name' => 'Staff Keuangan Karya Jasa', 'email' => 'staff.keuangan.karyajasa@example.com'],
                ['name' => 'Staff Operasional Karya Jasa', 'email' => 'staff.operasional.karyajasa@example.com'],
            ],
        ],
        [
            'nama' => 'Koperasi Abdi Sesama',
            'admin_nama' => 'Admin Primer Abdi Sesama',
            'admin_email' => 'admin.primer.abdisesama@example.com',
            'staff' => [
                ['name' => 'Staff Keuangan Abdi Sesama', 'email' => 'staff.keuangan.abdisesama@example.com'],
                ['name' => 'Staff Operasional Abdi Sesama', 'email' => 'staff.operasional.abdisesama@example.com'],
            ],
        ],
        [
            'nama' => 'Koperasi Sentosa',
            'admin_nama' => 'Admin Primer Sentosa',
            'admin_email' => 'admin.primer.sentosa@example.com',
            'staff' => [
                ['name' => 'Staff Keuangan Sentosa', 'email' => 'staff.keuangan.sentosa@example.com'],
                ['name' => 'Staff Operasional Sentosa', 'email' => 'staff.operasional.sentosa@example.com'],
            ],
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
                    $this->seedPrimerUsers($primer);
                }
            }, 3);
        } finally {
            Auth::forgetGuards();
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
    private function seedPrimerUsers(array $primer): void
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

        $koperasi = Koperasi::where('nama', $primer['nama'])->firstOrFail();
        $admin = User::where('email', $primer['admin_email'])->firstOrFail();

        if ((int) $admin->koperasi_id !== (int) $koperasi->id) {
            throw new \RuntimeException("Admin primer {$primer['admin_email']} tidak terhubung ke {$primer['nama']}.");
        }

        Auth::setUser($admin);

        try {
            // Role Staff harus dibuat per tenant karena nama role yang sama
            // tetap memiliki koperasi_id berbeda untuk setiap primer.
            $this->call(DemoStaffRoleSeeder::class);

            $staffRole = Role::query()
                ->where('name', 'Staff')
                ->where('guard_name', 'web')
                ->where('koperasi_id', $koperasi->id)
                ->firstOrFail();

            foreach ($primer['staff'] as $data) {
                $user = User::firstOrCreate(
                    ['email' => $data['email']],
                    [
                        'name' => $data['name'],
                        'password' => Hash::make((string) config('demo.user_password')),
                        'email_verified_at' => now(),
                    ],
                );
                $user->forceFill([
                    'name' => $data['name'],
                    'koperasi_id' => $koperasi->id,
                    'email_verified_at' => $user->email_verified_at ?? now(),
                ])->save();
                $user->syncRoles([$staffRole]);
            }
        } finally {
            Auth::forgetGuards();
        }
    }
}
