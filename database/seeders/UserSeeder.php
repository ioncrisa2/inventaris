<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Akun super_admin dibuat langsung di DatabaseSeeder (bukan di sini) —
     * dia tidak terikat koperasi manapun, sedangkan seeder ini berjalan
     * SETELAH DatabaseSeeder men-set Auth::setUser() ke akun admin_primer
     * koperasi demo, supaya semua user di bawah ini otomatis ikut ter-tag
     * koperasi_id koperasi demo lewat trait BelongsToKoperasi.
     */
    public function run(): void
    {
        $password = (string) config('demo.user_password');

        if (blank($password)) {
            throw new \RuntimeException('DEMO_USER_PASSWORD wajib diisi untuk membuat akun demo.');
        }

        $units = UnitKerja::pluck('id', 'nama_unit');
        $staffRole = Role::query()
            ->where('name', 'Staff')
            ->where('guard_name', 'web')
            ->where('koperasi_id', auth()->user()?->koperasi_id)
            ->firstOrFail();
        $users = [
            ['name' => 'Staff Teknologi Informasi', 'email' => 'it@example.com', 'unit' => 'IT', 'role' => 'Staff'],
            ['name' => 'Staff Keuangan', 'email' => 'staff@example.com', 'unit' => 'Keuangan', 'role' => 'Staff'],
            ['name' => 'Staff Sumber Daya Manusia', 'email' => 'sdm@example.com', 'unit' => 'SDM', 'role' => 'Staff'],
            ['name' => 'Staff Operasional', 'email' => 'operasional@example.com', 'unit' => 'Operasional', 'role' => 'Staff'],
            ['name' => 'Staff Bagian Umum', 'email' => 'umum@example.com', 'unit' => 'Bag. Umum', 'role' => 'Staff'],
            ['name' => 'Staff Logistik', 'email' => 'logistik@example.com', 'unit' => 'Logistik', 'role' => 'Staff'],
        ];

        foreach ($users as $data) {
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => Hash::make($password),
                    'email_verified_at' => now(),
                    'unit_kerja_id' => $units[$data['unit']],
                ],
            );
            $user->forceFill([
                'name' => $data['name'],
                'email_verified_at' => $user->email_verified_at ?? now(),
                'unit_kerja_id' => $units[$data['unit']],
            ])->save();
            $user->syncRoles([$staffRole]);
        }
    }
}
