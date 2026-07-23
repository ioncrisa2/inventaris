<?php

namespace Database\Seeders;

use App\Models\Karyawan;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AbsensiSeeder extends Seeder
{
    /**
     * Distribusi status harian (dalam persen) untuk hari kerja biasa.
     */
    private const DISTRIBUSI = [
        'Hadir' => 86,
        'Izin' => 4,
        'Sakit' => 4,
        'Cuti' => 2,
        'Dinas Luar Kota' => 2,
        'Alpha' => 2,
    ];

    public function run(): void
    {
        $karyawans = Karyawan::whereNull('tanggal_mengundurkan_diri')->get();

        // Enam bulan berjalan mencakup seluruh rentang 25-24 untuk tiga
        // periode payroll selesai terakhir, termasuk saat melewati tahun.
        $mulai = CarbonImmutable::now()->subMonthsNoOverflow(6)->startOfDay();
        $akhir = CarbonImmutable::now();

        $rows = [];
        $now = now();

        foreach ($karyawans as $karyawan) {
            for ($tanggal = $mulai; $tanggal->lte($akhir); $tanggal = $tanggal->addDay()) {
                // Minggu dianggap hari libur, tidak ada absensi.
                if ($tanggal->isSunday()) {
                    continue;
                }

                $status = $this->pilihStatus($karyawan, $tanggal);

                $rows[] = [
                    'karyawan_id' => $karyawan->id,
                    'tanggal' => $tanggal->toDateString(),
                    'status' => $status,
                    'catatan' => $this->catatanUntuk($status),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('absensi')->upsert(
                $chunk,
                ['karyawan_id', 'tanggal'],
                ['status', 'catatan', 'updated_at']
            );
        }
    }

    private function pilihStatus(Karyawan $karyawan, CarbonImmutable $tanggal): string
    {
        $roll = (crc32($karyawan->nik.'|'.$tanggal->toDateString()) % 100) + 1;
        $kumulatif = 0;

        foreach (self::DISTRIBUSI as $status => $persen) {
            $kumulatif += $persen;

            if ($roll <= $kumulatif) {
                return $status;
            }
        }

        return 'Hadir';
    }

    private function catatanUntuk(string $status): ?string
    {
        return match ($status) {
            'Izin' => 'Izin keperluan pribadi',
            'Sakit' => 'Sakit, ada surat keterangan',
            'Cuti' => 'Cuti karyawan',
            'Dinas Luar Kota' => 'Penugasan di luar kota',
            'Alpha' => null,
            default => null,
        };
    }
}
