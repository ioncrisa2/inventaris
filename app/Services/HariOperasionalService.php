<?php

namespace App\Services;

use App\Models\Pengaturan;
use App\Repositories\HariLiburRepository;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class HariOperasionalService
{
    public const SETTING_KEY = 'hari_operasional';

    /**
     * Default: Senin-Sabtu operasional, Minggu libur.
     */
    public const DEFAULT_HARI = [1, 2, 3, 4, 5, 6];

    /**
     * Nomor hari ISO (1=Senin ... 7=Minggu), dipakai untuk render checkbox.
     */
    public const NAMA_HARI = [
        1 => 'Senin',
        2 => 'Selasa',
        3 => 'Rabu',
        4 => 'Kamis',
        5 => 'Jumat',
        6 => 'Sabtu',
        7 => 'Minggu',
    ];

    public function __construct(private HariLiburRepository $hariLiburRepository) {}

    /**
     * @return list<int>
     */
    public function hariOperasional(): array
    {
        $mentah = json_decode(Pengaturan::get(self::SETTING_KEY) ?? '', true);

        if (! is_array($mentah) || $mentah === []) {
            return self::DEFAULT_HARI;
        }

        return $this->sanitasi($mentah);
    }

    /**
     * @param  array<int|string>  $hari
     */
    public function simpan(array $hari): void
    {
        Pengaturan::set(self::SETTING_KEY, json_encode($this->sanitasi($hari)));
    }

    /**
     * Cek murni pola hari-dalam-minggu (tidak memperhitungkan tanggal libur
     * nasional spesifik) — dipakai internal oleh liburDalamRentang() dan
     * tempat lain yang cuma perlu tahu jadwal mingguan.
     */
    public function isOperasional(Carbon $tanggal): bool
    {
        return in_array($tanggal->dayOfWeekIso, $this->hariOperasional(), true);
    }

    /**
     * Cek gabungan: tanggal ini libur kalau bukan hari operasional (jadwal
     * mingguan) ATAU terdaftar sebagai hari libur nasional. Ini pemeriksaan
     * satu tanggal (dipakai validasi input Absensi per submit).
     */
    public function isLiburPada(Carbon $tanggal): bool
    {
        return ! $this->isOperasional($tanggal) || $this->hariLiburRepository->ada($tanggal);
    }

    /**
     * Satu sumber kebenaran "tanggal mana saja yang libur" pada rentang
     * inklusif, dipakai bareng oleh kalender Absensi (AbsensiService::kalender())
     * dan perhitungan jumlah hari komponen gaji per_hari (jumlahHariOperasional()).
     * Satu query batch ke hari_libur, bukan query per hari.
     *
     * @return Collection<string, ?string> tanggal (Y-m-d) => keterangan libur
     *                                      nasional (null = libur mingguan biasa)
     */
    public function liburDalamRentang(Carbon $awal, Carbon $akhir): Collection
    {
        $hariOperasional = $this->hariOperasional();
        $liburNasional = $this->hariLiburRepository->tanggalDalamRentang($awal, $akhir);
        $hasil = collect();

        for ($tanggal = $awal->copy(); $tanggal->lte($akhir); $tanggal->addDay()) {
            $key = $tanggal->toDateString();

            if ($liburNasional->has($key)) {
                $hasil->put($key, $liburNasional->get($key));
            } elseif (! in_array($tanggal->dayOfWeekIso, $hariOperasional, true)) {
                $hasil->put($key, null);
            }
        }

        return $hasil;
    }

    /**
     * Jumlah hari operasional (bukan hari libur mingguan maupun libur
     * nasional) pada rentang tanggal inklusif, dipakai sebagai pengali
     * komponen gaji dengan metode_perhitungan = per_hari.
     */
    public function jumlahHariOperasional(Carbon $awal, Carbon $akhir): int
    {
        return ($awal->diffInDays($akhir) + 1) - $this->liburDalamRentang($awal, $akhir)->count();
    }

    /**
     * @param  array<int|string>  $hari
     * @return list<int>
     */
    private function sanitasi(array $hari): array
    {
        $hari = array_values(array_unique(array_map('intval', $hari)));
        $hari = array_values(array_filter($hari, fn (int $nilai) => $nilai >= 1 && $nilai <= 7));
        sort($hari);

        return $hari === [] ? self::DEFAULT_HARI : $hari;
    }
}
