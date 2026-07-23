<?php

namespace App\Services;

use App\Models\Absensi;
use App\Models\Karyawan;
use App\Repositories\AbsensiRepository;
use App\Repositories\KaryawanRepository;
use App\Support\PerPage;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AbsensiService
{
    public function __construct(
        private AbsensiRepository $absensiRepository,
        private KaryawanRepository $karyawanRepository,
        private DashboardCache $dashboardCache,
    ) {}

    public function daftarKaryawan(?string $search, int $perPage = PerPage::DEFAULT): LengthAwarePaginator
    {
        return $this->karyawanRepository->searchOrderedByName($search, $perPage);
    }

    /**
     * Susun data kalender absensi satu bulan penuh untuk satu karyawan:
     * grid minggu (Senin-Minggu), status tiap hari, dan rekap total per status.
     *
     * @return array{
     *     namaBulan: string,
     *     mingguKalender: list<list<array<string, mixed>>>,
     *     totalHadir: int,
     *     totalIzin: int,
     *     totalSakit: int,
     *     totalCuti: int,
     *     totalDinasLuarKota: int,
     *     totalAlpha: int,
     * }
     */
    public function kalender(Karyawan $karyawan, int $bulan, int $tahun): array
    {
        $awalBulan = Carbon::createFromDate($tahun, $bulan, 1);
        $akhirBulan = $awalBulan->copy()->endOfMonth();

        $absensiPerTanggal = $this->absensiRepository->forMonth($karyawan->id, $bulan, $tahun);

        $totalPerStatus = $this->totalPerStatus($absensiPerTanggal);

        $today = today();
        $mulaiGrid = $awalBulan->copy()->startOfWeek(Carbon::MONDAY);
        $akhirGrid = $akhirBulan->copy()->endOfWeek(Carbon::SUNDAY);

        $mingguKalender = [];
        $tanggalBerjalan = $mulaiGrid->copy();

        while ($tanggalBerjalan->lte($akhirGrid)) {
            $minggu = [];

            for ($i = 0; $i < 7; $i++) {
                $minggu[] = [
                    'tanggal' => $tanggalBerjalan->copy(),
                    'di_luar_bulan' => ! $tanggalBerjalan->isSameMonth($awalBulan),
                    'hari_minggu' => $tanggalBerjalan->isSunday(),
                    'masa_depan' => $tanggalBerjalan->gt($today),
                    'absensi' => $absensiPerTanggal->get($tanggalBerjalan->format('Y-m-d')),
                ];

                $tanggalBerjalan->addDay();
            }

            $mingguKalender[] = $minggu;
        }

        return [
            'namaBulan' => $awalBulan->translatedFormat('F'),
            'mingguKalender' => $mingguKalender,
            ...$totalPerStatus,
        ];
    }

    /**
     * @return array{totalHadir: int, totalIzin: int, totalSakit: int, totalCuti: int, totalDinasLuarKota: int, totalAlpha: int}
     */
    private function totalPerStatus(Collection $absensiPerTanggal): array
    {
        $jumlah = $absensiPerTanggal->countBy('status');

        return [
            'totalHadir' => (int) $jumlah->get('Hadir', 0),
            'totalIzin' => (int) $jumlah->get('Izin', 0),
            'totalSakit' => (int) $jumlah->get('Sakit', 0),
            'totalCuti' => (int) $jumlah->get('Cuti', 0),
            'totalDinasLuarKota' => (int) $jumlah->get('Dinas Luar Kota', 0),
            'totalAlpha' => (int) $jumlah->get('Alpha', 0),
        ];
    }

    public function simpan(Karyawan $karyawan, array $validated): Absensi
    {
        return DB::transaction(function () use ($karyawan, $validated) {
            $absensi = $this->absensiRepository->simpanUntukTanggal($karyawan->id, $validated['tanggal'], [
                'status' => $validated['status'],
                'catatan' => $validated['catatan'] ?? null,
            ]);

            $this->dashboardCache->invalidateAfterCommit();

            return $absensi;
        }, 3);
    }
}
