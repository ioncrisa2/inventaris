<?php

namespace App\Services;

use App\Models\HariLibur;
use App\Repositories\HariLiburRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class HariLiburSinkronisasiService
{
    public function __construct(
        private NagerDateClient $nagerDateClient,
        private HariLiburRepository $hariLiburRepository,
    ) {}

    /**
     * Bandingkan hari libur nasional dari API untuk satu tahun dengan data
     * yang sudah ada di database. Tidak menulis apa pun ke database — cuma
     * membaca & memisahkan hasilnya jadi dua kelompok.
     *
     * @return array{baru: list<array{tanggal: string, keterangan: string}>, sudahAda: list<array{tanggal: string, keterangan: string}>}
     */
    public function bandingkan(int $tahun): array
    {
        $dariApi = $this->nagerDateClient->publicHolidays($tahun);
        $tanggalDiDb = $this->hariLiburRepository->tanggalDalamRentang(
            Carbon::create($tahun, 1, 1),
            Carbon::create($tahun, 12, 31),
        );

        $baru = [];
        $sudahAda = [];

        foreach ($dariApi as $item) {
            if ($tanggalDiDb->has($item['tanggal'])) {
                $sudahAda[] = $item;
            } else {
                $baru[] = $item;
            }
        }

        return ['baru' => $baru, 'sudahAda' => $sudahAda];
    }

    /**
     * Simpan tanggal-tanggal terpilih dari API. Fetch ulang API sebagai
     * sumber kebenaran konten (client cuma mengirim tanggal mana yang
     * dipilih, bukan keterangannya). Tanggal yang sudah ada di database
     * tidak pernah diubah — cuma menambah yang benar-benar belum ada.
     *
     * @param  list<string>  $tanggalTerpilih
     * @return int jumlah baris yang benar-benar dibuat
     */
    public function terapkan(int $tahun, array $tanggalTerpilih): int
    {
        $dariApi = collect($this->nagerDateClient->publicHolidays($tahun))->keyBy('tanggal');
        $tanggalTerpilih = array_values(array_unique($tanggalTerpilih));

        return DB::transaction(function () use ($dariApi, $tanggalTerpilih) {
            $jumlah = 0;

            foreach ($tanggalTerpilih as $tanggal) {
                $item = $dariApi->get($tanggal);

                if (! $item) {
                    continue;
                }

                $hariLibur = HariLibur::firstOrCreate(
                    ['tanggal' => $item['tanggal']],
                    ['keterangan' => $item['keterangan']],
                );

                if ($hariLibur->wasRecentlyCreated) {
                    $jumlah++;
                }
            }

            return $jumlah;
        }, 3);
    }
}
