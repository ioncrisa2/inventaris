<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('DatabaseSeeder berisi akun dan data demo sehingga hanya boleh dijalankan di local atau testing.');
        }

        try {
            DB::transaction(fn () => $this->call([
                PermissionSeeder::class,
                UnitKerjaSeeder::class,
                PengaturanSeeder::class,
                UserSeeder::class,
                KaryawanSeeder::class,
                AbsensiSeeder::class,
                BarangSeeder::class,
                RiwayatKondisiBarangSeeder::class,
                KaryawanMediaSeeder::class,
                BarangMediaSeeder::class,
                KomponenGajiSeeder::class,
                TransaksiGajiSeeder::class,
            ]), 3);
        } finally {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }
}
