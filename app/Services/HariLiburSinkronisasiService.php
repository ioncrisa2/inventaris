<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\HariLiburRepository;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class HariLiburSinkronisasiService
{
    public function __construct(
        private TanggalMerahClient $tanggalMerahClient,
        private HariLiburRepository $hariLiburRepository,
    ) {}

    /**
     * @return array{
     *     baru: list<array{tanggal: string, keterangan: string, jenis: 'holiday'|'leave'}>,
     *     sudahAda: list<array{tanggal: string, keterangan: string, jenis: 'holiday'|'leave'}>,
     *     snapshot: string
     * }
     */
    public function bandingkan(int $koperasiId, int $tahun): array
    {
        $dariApi = $this->tanggalMerahClient->holidays($tahun);
        $tanggalDiDatabase = $this->hariLiburRepository->tanggalDalamRentangUntukKoperasi(
            Carbon::create($tahun, 1, 1),
            Carbon::create($tahun, 12, 31),
            $koperasiId,
        );

        $baru = [];
        $sudahAda = [];
        $snapshot = $this->buatSnapshot($koperasiId, $tahun, $dariApi);

        foreach ($dariApi as $item) {
            if ($tanggalDiDatabase->has($item['tanggal'])) {
                $sudahAda[] = $item;
            } else {
                $baru[] = $item;
            }
        }

        return compact('baru', 'sudahAda', 'snapshot');
    }

    /**
     * @param  list<string>  $tanggalTerpilih
     */
    public function terapkan(
        User $aktor,
        int $koperasiId,
        int $tahun,
        array $tanggalTerpilih,
        string $snapshotDitinjau,
    ): int {
        if (! $aktor->isSuperAdmin()) {
            throw new AuthorizationException('Hanya super admin yang dapat menyinkronkan hari libur.');
        }

        $responsTerbaru = $this->tanggalMerahClient->holidays($tahun);

        if (! hash_equals($snapshotDitinjau, $this->buatSnapshot($koperasiId, $tahun, $responsTerbaru))) {
            throw new \RuntimeException('Data API berubah sejak pratinjau ditampilkan. Muat ulang pratinjau dan periksa kembali sebelum menerapkan.');
        }

        $dariApi = collect($responsTerbaru)->keyBy('tanggal');
        $tanggalTerpilih = array_values(array_unique($tanggalTerpilih));
        $dataTerpilih = collect($tanggalTerpilih)
            ->map(fn (string $tanggal) => $dariApi->get($tanggal))
            ->filter()
            ->values()
            ->all();

        return DB::transaction(
            fn () => $this->hariLiburRepository->insertMissingUntukKoperasi($koperasiId, $dataTerpilih),
            3,
        );
    }

    /**
     * @param  list<array{tanggal: string, keterangan: string, jenis: 'holiday'|'leave'}>  $items
     */
    private function buatSnapshot(int $koperasiId, int $tahun, array $items): string
    {
        $payload = json_encode([
            'koperasi_id' => $koperasiId,
            'tahun' => $tahun,
            'items' => collect($items)->sortBy('tanggal')->values()->all(),
        ], JSON_THROW_ON_ERROR);

        return hash_hmac('sha256', $payload, (string) config('app.key'));
    }
}
