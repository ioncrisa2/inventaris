<?php

namespace Database\Seeders;

use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $password = (string) config('demo.user_password');

        if (blank($password)) {
            throw new \RuntimeException('DEMO_USER_PASSWORD wajib diisi untuk membuat akun demo.');
        }

        $units = UnitKerja::pluck('id', 'nama_unit');
        $users = [
            ['name' => 'Administrator', 'email' => 'admin@example.com', 'unit' => 'IT', 'role' => 'Admin'],
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
            $user->syncRoles([$data['role']]);
        }
    }
}
