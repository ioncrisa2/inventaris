<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\MediaBackfillService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
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
            DB::transaction(function () {
                // Membuat 4 koperasi primer dengan tepat 3 akun per primer:
                // satu admin_primer dan dua Staff. Akun global system_owner
                // yang mungkin sudah diprovisikan tidak disentuh, sedangkan
                // data super_admin tetap sama seperti seeder sebelumnya.
                $this->call(MultiPrimerUserSeeder::class);

                // Dari titik ini, semua model yang pakai trait BelongsToKoperasi
                // otomatis ter-tag koperasi_id milik Koperasi Rukun —
                // seeder-seeder di bawah TIDAK perlu tahu soal koperasi_id sama
                // sekali, sama seperti kalau admin_primer ini benar-benar login
                // dan mengisi data lewat UI. Data operasional cukup dibuat pada
                // satu primer; tiga primer lain tetap tersedia untuk uji akun
                // dan isolasi tenant tanpa melipatgandakan waktu seeding.
                Auth::setUser(User::where('email', 'admin.primer.rukun@example.com')->firstOrFail());

                $this->call([
                    UnitKerjaSeeder::class,
                    PengaturanSeeder::class,
                    HariLiburSeeder::class,
                    KaryawanSeeder::class,
                    AbsensiSeeder::class,
                    BarangSeeder::class,
                    RiwayatKondisiBarangSeeder::class,
                    KaryawanMediaSeeder::class,
                    BarangMediaSeeder::class,
                    KomponenGajiSeeder::class,
                    TransaksiGajiSeeder::class,
                ]);

                $media = app(MediaBackfillService::class)->run(chunk: 200);
                if (($media['unreadable'] + $media['invalid']) > 0) {
                    throw new \RuntimeException('Sebagian file demo gagal dicatat ke registry media.');
                }

                Auth::forgetGuards();
            }, 3);
        } finally {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }
}
