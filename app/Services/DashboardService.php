<?php

namespace App\Services;

use App\Models\Absensi;
use App\Models\User;
use App\Repositories\DashboardRepository;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class DashboardService
{
    public function __construct(
        private DashboardRepository $dashboardRepository,
        private DashboardCache $dashboardCache,
    ) {}

    /**
     * Tiap kartu/grafik/tabel dashboard punya permission "dashboard.*.view"
     * masing-masing, jadi datanya hanya dihitung & dikirim ke view kalau
     * user memang berhak melihat widget itu.
     */
    public function widgets(User $user, ?string $periode = null): array
    {
        $bolehTotalBarang = $user->can('dashboard.total-inventaris.view');
        $bolehNilaiInventaris = $user->can('dashboard.nilai-aset.view');
        $ringkasanInventaris = $bolehTotalBarang || $bolehNilaiInventaris
            ? $this->dashboardCache->remember('ringkasan-inventaris', fn () => $this->dashboardRepository->ringkasanInventaris())
            : null;

        $totalBarang = $bolehTotalBarang
            ? $ringkasanInventaris['total']
            : null;

        $totalNilaiInventaris = $bolehNilaiInventaris
            ? $ringkasanInventaris['nilai']
            : null;

        $karyawanAktif = $user->can('dashboard.karyawan-aktif.view')
            ? $this->dashboardCache->remember('karyawan-aktif', fn () => $this->dashboardRepository->karyawanAktifCount())
            : null;

        $bolehPerluPerbaikan = $user->can('dashboard.perlu-perbaikan.view');
        $bolehKondisiInventaris = $user->can('dashboard.kondisi-inventaris.view');
        $ringkasanKondisi = $bolehPerluPerbaikan || $bolehKondisiInventaris
            ? $this->dashboardCache->remember('kondisi-inventaris', fn () => $this->dashboardRepository->ringkasanKondisiInventaris())
            : ['grup' => [], 'perluPerbaikan' => 0];

        $barangPerluPerbaikan = $bolehPerluPerbaikan
            ? $ringkasanKondisi['perluPerbaikan']
            : null;

        $trenAbsensi = $user->can('dashboard.tren-absensi.view')
            ? $this->trenAbsensi($periode)
            : null;

        $kondisiInventaris = $bolehKondisiInventaris ? $ringkasanKondisi['grup'] : [];

        $dataBelumLengkap = $user->can('dashboard.data-belum-lengkap.view')
            ? $this->dashboardCache->remember('data-belum-lengkap', fn () => $this->dashboardRepository->dataBelumLengkap())
            : [];

        return compact(
            'totalBarang',
            'totalNilaiInventaris',
            'karyawanAktif',
            'barangPerluPerbaikan',
            'trenAbsensi',
            'kondisiInventaris',
            'dataBelumLengkap',
        );
    }

    /**
     * Siklus penggajian berjalan dari tanggal 25 sampai tanggal 24 bulan
     * berikutnya. Parameter periode hanya diterima bila tepat menunjuk
     * tanggal 25, supaya tombol navigasi tidak dapat menghasilkan rentang liar.
     */
    private function trenAbsensi(?string $periode): array
    {
        $hariIni = CarbonImmutable::today();
        $mulai = $this->mulaiPeriode($periode, $hariIni);

        return $this->dashboardCache->remember(
            'tren-absensi:'.$mulai->toDateString(),
            fn () => $this->bangunTrenAbsensi($mulai, $hariIni),
        );
    }

    private function bangunTrenAbsensi(CarbonImmutable $mulai, CarbonImmutable $hariIni): array
    {
        $selesai = $mulai->addMonth()->day(24);
        $mulaiSebelumnya = $mulai->subMonth();
        $selesaiSebelumnya = $mulai->subDay();

        $absensiDuaPeriode = $this->dashboardRepository->absensiHarian($mulaiSebelumnya, $selesai);
        $absensiSekarang = collect($absensiDuaPeriode)
            ->filter(fn ($nilai, $tanggal) => $tanggal >= $mulai->toDateString())
            ->all();
        $absensiSebelumnya = collect($absensiDuaPeriode)
            ->filter(fn ($nilai, $tanggal) => $tanggal <= $selesaiSebelumnya->toDateString())
            ->all();
        $tanggalSekarang = $this->rentangTanggal($mulai, $selesai);
        $tanggalSebelumnya = $this->rentangTanggal($mulaiSebelumnya, $selesaiSebelumnya);
        $jumlahHari = max(count($tanggalSekarang), count($tanggalSebelumnya));
        $status = Absensi::STATUSES;
        $seri = [];

        foreach ($status as $namaStatus) {
            $seri[$namaStatus] = [];

            foreach ($tanggalSekarang as $tanggal) {
                $seri[$namaStatus][] = $tanggal->isAfter($hariIni) && $hariIni->betweenIncluded($mulai, $selesai)
                    ? null
                    : ($absensiSekarang[$tanggal->toDateString()][$namaStatus] ?? 0);
            }

            while (count($seri[$namaStatus]) < $jumlahHari) {
                $seri[$namaStatus][] = null;
            }
        }

        $hadirSebelumnya = [];
        foreach ($tanggalSebelumnya as $tanggal) {
            $hadirSebelumnya[] = $absensiSebelumnya[$tanggal->toDateString()]['Hadir'] ?? 0;
        }
        while (count($hadirSebelumnya) < $jumlahHari) {
            $hadirSebelumnya[] = null;
        }

        $labels = array_map(
            fn (CarbonInterface $tanggal) => $tanggal->locale('id')->translatedFormat('d M'),
            $tanggalSekarang,
        );
        $akhirPekan = array_map(
            fn (CarbonInterface $tanggal) => $tanggal->isWeekend(),
            $tanggalSekarang,
        );
        while (count($labels) < $jumlahHari) {
            $labels[] = '';
            $akhirPekan[] = false;
        }

        $ringkasan = collect($status)->mapWithKeys(fn ($namaStatus) => [
            $namaStatus => (int) collect($seri[$namaStatus])->filter(fn ($nilai) => $nilai !== null)->sum(),
        ])->all();
        $totalTercatat = array_sum($ringkasan);

        return [
            'periode' => $this->labelPeriode($mulai, $selesai),
            'periodeSebelumnya' => $this->labelPeriode($mulaiSebelumnya, $selesaiSebelumnya),
            'statusPeriode' => $hariIni->isAfter($selesai)
                ? 'Selesai'
                : ($hariIni->isBefore($mulai) ? 'Akan datang' : 'Berjalan'),
            'periodeSebelumnyaQuery' => $mulaiSebelumnya->toDateString(),
            'periodeBerikutnyaQuery' => $mulai->addMonth()->toDateString(),
            'labels' => $labels,
            'tanggalSekarang' => array_map(fn (CarbonInterface $tanggal) => $this->labelTanggal($tanggal), $tanggalSekarang),
            'tanggalSebelumnya' => array_map(fn (CarbonInterface $tanggal) => $this->labelTanggal($tanggal), $tanggalSebelumnya),
            'akhirPekan' => $akhirPekan,
            'seri' => $seri,
            'hadirSebelumnya' => $hadirSebelumnya,
            'ringkasan' => $ringkasan,
            'persentaseHadir' => $totalTercatat > 0
                ? round(($ringkasan['Hadir'] / $totalTercatat) * 100, 1)
                : 0,
        ];
    }

    private function mulaiPeriode(?string $periode, CarbonImmutable $hariIni): CarbonImmutable
    {
        if ($periode && preg_match('/^\d{4}-\d{2}-25$/', $periode)) {
            try {
                return CarbonImmutable::createFromFormat('!Y-m-d', $periode);
            } catch (\Throwable) {
                // Gunakan periode berjalan jika parameter tanggal tidak valid.
            }
        }

        return $hariIni->day >= 25
            ? $hariIni->startOfMonth()->day(25)
            : $hariIni->subMonth()->startOfMonth()->day(25);
    }

    /** @return list<CarbonImmutable> */
    private function rentangTanggal(CarbonImmutable $mulai, CarbonImmutable $selesai): array
    {
        $tanggal = [];

        for ($cursor = $mulai; $cursor->lte($selesai); $cursor = $cursor->addDay()) {
            $tanggal[] = $cursor;
        }

        return $tanggal;
    }

    private function labelPeriode(CarbonInterface $mulai, CarbonInterface $selesai): string
    {
        return $this->labelTanggal($mulai).' – '.$this->labelTanggal($selesai);
    }

    private function labelTanggal(CarbonInterface $tanggal): string
    {
        return $tanggal->locale('id')->translatedFormat('d M Y');
    }
}
