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
     * Jumlah hari berstatus Hadir pada periode penggajian bulan/tahun tsb,
     * dipakai sebagai pengali komponen gaji dengan metode_perhitungan =
     * per_kehadiran. Periode gajian BUKAN kalender bulan biasa: siklus
     * gajian dibayar tiap tanggal 25, jadi "periode Juli" mencakup absensi
     * dari 25 Juni s.d. 24 Juli.
     */
    public function hitungHadir(int $karyawanId, int $bulan, int $tahun): int
    {
        [$mulai, $akhir] = $this->periodeGajian($bulan, $tahun);

        return Absensi::where('karyawan_id', $karyawanId)
            ->where('status', 'Hadir')
            ->where('tanggal', '>=', $mulai->toDateString())
            ->where('tanggal', '<', $akhir->copy()->addDay()->toDateString())
            ->count();
    }

    /**
     * @return array{0: Carbon, 1: Carbon} [$mulai, $akhir]
     */
    private function periodeGajian(int $bulan, int $tahun): array
    {
        $awalBulanIni = Carbon::create($tahun, $bulan, 1);
        $mulai = $awalBulanIni->copy()->subMonthNoOverflow()->day(25);
        $akhir = $awalBulanIni->copy()->day(24);

        return [$mulai, $akhir];
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
