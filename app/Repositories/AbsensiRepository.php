<?php

namespace App\Repositories;

use App\Models\Absensi;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AbsensiRepository
{
    public function forMonth(int $karyawanId, int $bulan, int $tahun): Collection
    {
        $mulai = Carbon::create($tahun, $bulan, 1)->startOfDay();
        $selesaiEksklusif = $mulai->copy()->addMonthNoOverflow();

        return Absensi::where('karyawan_id', $karyawanId)
            ->where('tanggal', '>=', $mulai->toDateString())
            ->where('tanggal', '<', $selesaiEksklusif->toDateString())
            ->get()
            ->keyBy(fn (Absensi $absensi) => $absensi->tanggal->format('Y-m-d'));
    }

    /**
     * Rentang setengah terbuka tetap cocok pada MySQL DATE dan SQLite yang dapat
     * menyimpan nilai cast tanggal dengan komponen waktu.
     */
    public function simpanUntukTanggal(int $karyawanId, string $tanggal, array $atribut): Absensi
    {
        $absensi = Absensi::where('karyawan_id', $karyawanId)
            ->where('tanggal', '>=', Carbon::parse($tanggal)->startOfDay()->toDateString())
            ->where('tanggal', '<', Carbon::parse($tanggal)->startOfDay()->addDay()->toDateString())
            ->lockForUpdate()
            ->first();

        if ($absensi) {
            $absensi->update($atribut);

            return $absensi;
        }

        return Absensi::create([
            'karyawan_id' => $karyawanId,
            'tanggal' => $tanggal,
            ...$atribut,
        ]);
    }
}
