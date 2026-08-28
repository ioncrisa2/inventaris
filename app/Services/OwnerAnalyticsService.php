<?php

namespace App\Services;

use App\Models\Absensi;
use App\Repositories\OwnerAnalyticsRepository;
use App\Support\OwnerAnalyticsPrivacy;
use App\Support\PenyusutanCalculator;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class OwnerAnalyticsService
{
    public const CACHE_VERSION = 'v1';

    public const CACHE_TTL_SECONDS = 600;

    public function __construct(
        private OwnerAnalyticsRepository $repository,
        private OwnerAnalyticsPrivacy $privacy,
    ) {}

    /**
     * Payload ringkas untuk `owner.dashboard`.
     *
     * Struktur utama: `diperbaruiPada`, `meta`, `kartu`, `platform`,
     * `grafik`, `perbandinganKoperasi`, dan `pilihanKoperasi`.
     */
    public function dashboard(array $filters = []): array
    {
        $filters = $this->normaliseFilters([...$filters, 'koperasi_id' => null, 'modul' => 'semua']);

        return $this->remember('dashboard-global', $filters, fn () => $this->buildGlobal($filters, true));
    }

    /**
     * Payload analitik global atau satu koperasi bila `koperasi_id` diberikan.
     * Semua nilai uang merupakan decimal string.
     */
    public function analytics(array $filters = []): array
    {
        $filters = $this->normaliseFilters($filters);

        if ($filters['koperasi_id'] !== null) {
            return $this->koperasi($filters['koperasi_id'], $filters);
        }

        return $this->remember('analytics-global', $filters, fn () => $this->buildGlobal($filters, false));
    }

    /**
     * Payload `owner.analytics.koperasi`. Tidak ada model atau ID record
     * operasional di dalam hasil; hanya metadata koperasi dan agregat.
     */
    public function koperasi(int $koperasiId, array $filters = []): array
    {
        $filters = $this->normaliseFilters([...$filters, 'koperasi_id' => $koperasiId]);

        return $this->remember(
            'analytics-koperasi-'.$koperasiId,
            $filters,
            fn () => $this->buildKoperasi($koperasiId, $filters),
        );
    }

    /**
     * Cache key sengaja tidak memakai DashboardCache/bucket tenant.
     *
     * @param  array<string, mixed>  $filters
     */
    public function cacheKey(string $scope, array $filters): string
    {
        ksort($filters);
        $fingerprint = hash('sha256', (string) json_encode($filters, JSON_THROW_ON_ERROR));

        return 'owner-analytics:'.self::CACHE_VERSION.':'.$scope.':'.$fingerprint;
    }

    /**
     * @param  array{tanggal_awal: string, tanggal_akhir: string, koperasi_id: ?int, modul: string}  $filters
     */
    private function buildGlobal(array $filters, bool $dashboard): array
    {
        [$start, $end] = $this->dates($filters);
        $inventory = $this->wants($filters, 'inventaris') ? $this->inventorySection(null, $start, $end) : null;
        $employees = $this->wants($filters, 'kepegawaian') ? $this->employeeSection(null, $start, $end) : null;
        $attendance = $this->wants($filters, 'absensi') ? $this->attendanceSection(null, $start, $end) : null;
        $payroll = $this->wants($filters, 'penggajian') ? $this->payrollSection(null, $start, $end) : null;
        $users = $this->repository->tenantUserSummary();
        $koperasi = $this->repository->koperasiSummary($end->endOfDay());
        $growth = $this->growthChart(null, $start, $end);

        $payload = [
            'diperbaruiPada' => now()->toIso8601String(),
            'meta' => $this->meta('global', $filters),
            'kartu' => $this->globalCards($koperasi, $users, $inventory, $employees, $attendance, $payroll),
            'platform' => [
                'koperasi' => $koperasi,
                'akunTenant' => $users,
                'inventaris' => $inventory['ringkasan'] ?? null,
                'kepegawaian' => $employees['ringkasan'] ?? null,
                'absensi' => $attendance['ringkasan'] ?? null,
                'penggajian' => $payroll['ringkasan'] ?? null,
            ],
            'grafik' => [
                'pertumbuhan' => $growth,
                'absensi' => $attendance['grafik'] ?? null,
                'penggajian' => $payroll['grafik'] ?? null,
            ],
            'distribusi' => [
                'kondisiInventaris' => $inventory['distribusi']['kondisi'] ?? [],
                'statusKepegawaian' => $employees['distribusi']['status'] ?? [],
            ],
            'perbandinganKoperasi' => $filters['modul'] === 'semua'
                ? $this->normaliseComparison($this->repository->cooperativeComparison($start, $end))
                : [],
            'pilihanKoperasi' => $this->repository->koperasiOptions(),
        ];

        if (! $dashboard) {
            $payload['inventaris'] = $inventory;
            $payload['kepegawaian'] = $employees;
            $payload['absensi'] = $attendance;
            $payload['penggajian'] = $payroll;
        }

        return $payload;
    }

    /**
     * @param  array{tanggal_awal: string, tanggal_akhir: string, koperasi_id: ?int, modul: string}  $filters
     */
    private function buildKoperasi(int $koperasiId, array $filters): array
    {
        $koperasi = $this->repository->koperasiMetadata($koperasiId);
        if ($koperasi === null) {
            throw (new ModelNotFoundException)->setModel('koperasi', [$koperasiId]);
        }

        [$start, $end] = $this->dates($filters);
        $inventory = $this->wants($filters, 'inventaris') ? $this->inventorySection($koperasiId, $start, $end) : null;
        $employees = $this->wants($filters, 'kepegawaian') ? $this->employeeSection($koperasiId, $start, $end) : null;
        $attendance = $this->wants($filters, 'absensi') ? $this->attendanceSection($koperasiId, $start, $end) : null;
        $payroll = $this->wants($filters, 'penggajian') ? $this->payrollSection($koperasiId, $start, $end) : null;

        return [
            'diperbaruiPada' => now()->toIso8601String(),
            'meta' => $this->meta('koperasi', $filters),
            'koperasi' => $koperasi,
            'kartu' => $this->koperasiCards($inventory, $employees, $attendance, $payroll),
            'inventaris' => $inventory,
            'kepegawaian' => $employees,
            'absensi' => $attendance,
            'penggajian' => $payroll,
            'grafik' => [
                'pertumbuhan' => $this->growthChart($koperasiId, $start, $end),
                'absensi' => $attendance['grafik'] ?? null,
                'penggajian' => $payroll['grafik'] ?? null,
                'pergerakanKaryawan' => $employees['grafik'] ?? null,
                'penambahanBarang' => $inventory['grafik'] ?? null,
            ],
            'pilihanKoperasi' => $this->repository->koperasiOptions(),
        ];
    }

    private function inventorySection(?int $koperasiId, CarbonInterface $start, CarbonInterface $end): array
    {
        $summary = $this->repository->inventorySummary($koperasiId, $end);
        $depreciation = $this->depreciationSummary($koperasiId, $end);
        $growth = $this->repository->growthByMonth($koperasiId, $start, $end)['barang'];
        $months = $this->monthKeys($start, $end);

        return [
            'ringkasan' => [
                'total_barang' => $summary['total_barang'],
                'total_harga_perolehan' => $this->privacy->money($summary['total_harga_perolehan']),
                ...$depreciation,
            ],
            'distribusi' => [
                'kategori' => $this->plainDistribution($this->repository->inventoryDimensionDistribution('kategori', $koperasiId, $end)),
                'jenis' => $this->plainDistribution($this->repository->inventoryDimensionDistribution('jenis_barang', $koperasiId, $end)),
                'kondisi' => $this->plainDistribution($this->repository->inventoryConditionDistribution($koperasiId, $end)),
            ],
            'dataBelumLengkap' => $this->repository->inventoryCompleteness($koperasiId, $end),
            'grafik' => $this->chart(
                $months,
                [['key' => 'barang_baru', 'label' => 'Barang baru', 'data' => $this->integerSeries($months, $growth)]],
            ),
        ];
    }

    private function employeeSection(?int $koperasiId, CarbonInterface $start, CarbonInterface $end): array
    {
        $summary = $this->repository->employeeSummary($koperasiId, $end);
        $completeness = $this->repository->employeeCompleteness($koperasiId, $end);
        $movements = $this->repository->employeeMovementTrend($koperasiId, $start, $end);
        $months = $this->monthKeys($start, $end);
        $completenessPercentage = $this->privacy->percentage($completeness['lengkap'], $completeness['total_aktif']);

        return [
            'ringkasan' => [
                ...$summary,
                'kelengkapan_data' => $this->privacy->sensitiveValue(
                    $completenessPercentage,
                    $completeness['total_aktif'],
                ),
            ],
            'distribusi' => [
                'unitKerja' => $this->privacy->suppressDistribution(
                    $this->repository->employeeDistribution('unit_kerja', $koperasiId, $end),
                ),
                'status' => $this->privacy->suppressDistribution(
                    $this->repository->employeeDistribution('status_karyawan', $koperasiId, $end),
                ),
            ],
            'grafik' => $this->chart($months, [
                ['key' => 'masuk', 'label' => 'Karyawan masuk', 'data' => $this->integerSeries($months, $movements['masuk'])],
                ['key' => 'keluar', 'label' => 'Karyawan keluar', 'data' => $this->integerSeries($months, $movements['keluar'])],
            ]),
        ];
    }

    private function attendanceSection(?int $koperasiId, CarbonInterface $start, CarbonInterface $end): array
    {
        $summary = $this->repository->attendanceSummary($koperasiId, $start, $end);
        $byMonth = $this->repository->attendanceByMonth($koperasiId, $start, $end);
        $months = $this->monthKeys($start, $end);
        $cohortSafe = $summary['karyawan_tercatat'] >= OwnerAnalyticsPrivacy::MINIMUM_COHORT;
        $present = (int) ($summary['per_status']['Hadir'] ?? 0);
        $percentage = $this->privacy->sensitiveValue(
            $this->privacy->percentage($present, $summary['total']),
            $summary['karyawan_tercatat'],
        );
        $statusDistribution = $cohortSafe
            ? $this->plainDistribution($summary['per_status'])
            : [[
                'label' => 'Status kehadiran',
                'total' => null,
                'disembunyikan' => true,
                'pesan' => OwnerAnalyticsPrivacy::SUPPRESSION_MESSAGE,
            ]];
        $series = [];

        foreach (Absensi::STATUSES as $status) {
            $data = [];
            foreach ($months as $month) {
                $data[] = $cohortSafe ? (int) ($byMonth[$month][$status] ?? 0) : null;
            }
            $series[] = ['key' => $status, 'label' => $status, 'data' => $data];
        }

        return [
            'ringkasan' => [
                'total_pencatatan' => $summary['total'],
                'persentase_hadir' => $percentage,
                'status' => $statusDistribution,
            ],
            'grafik' => $this->chart($months, $series),
        ];
    }

    private function payrollSection(?int $koperasiId, CarbonInterface $start, CarbonInterface $end): array
    {
        $summary = $this->normalisePayroll($this->repository->payrollSummary($koperasiId, $start, $end));
        [$previousStart, $previousEnd] = $this->previousPeriod($start, $end);
        $previous = $this->normalisePayroll($this->repository->payrollSummary($koperasiId, $previousStart, $previousEnd));
        $byMonth = $this->repository->payrollByMonth($koperasiId, $start, $end);
        $months = $this->monthKeys($start, $end);
        $average = $this->privacy->sensitiveValue(
            $this->privacy->averageMoney($summary['total_gaji_bersih'], $summary['total_transaksi']),
            $summary['total_transaksi'],
        );

        return [
            'ringkasan' => [
                ...$summary,
                'rata_rata_gaji_bersih' => $average,
                'perubahan_gaji_bersih_persen' => $this->privacy->sensitiveValue(
                    $this->percentageChange(
                        $summary['total_gaji_bersih'],
                        $previous['total_gaji_bersih'],
                    ),
                    min($summary['total_transaksi'], $previous['total_transaksi']),
                ),
            ],
            'grafik' => $this->chart($months, [[
                'key' => 'gaji_bersih',
                'label' => 'Total gaji bersih',
                'format' => 'rupiah',
                'data' => array_map(
                    fn (string $month) => $this->privacy->money($byMonth[$month]['total_gaji_bersih'] ?? '0'),
                    $months,
                ),
            ]]),
        ];
    }

    private function depreciationSummary(?int $koperasiId, CarbonInterface $asOf): array
    {
        $totalDepreciation = '0.00';
        $bookValue = '0.00';
        $unrecognised = 0;

        foreach ($this->repository->inventoryDepreciationCohorts($koperasiId, $asOf) as $cohort) {
            try {
                $calculation = PenyusutanCalculator::hitungTahunan(
                    $cohort['kategori'],
                    $this->privacy->money($cohort['harga_perolehan']),
                    Carbon::createFromFormat('!Y-m-d', $cohort['bulan_perolehan'].'-01'),
                    $asOf->year,
                );
                $totalDepreciation = bcadd($totalDepreciation, $calculation['akumulasi_akhir_tahun'], 2);
                $bookValue = bcadd($bookValue, $calculation['nilai_buku_akhir_tahun'], 2);
            } catch (\InvalidArgumentException) {
                $unrecognised += $cohort['total_barang'];
            }
        }

        return [
            'total_penyusutan_akhir_tahun' => $totalDepreciation,
            'nilai_buku_akhir_tahun' => $bookValue,
            'tahun_penilaian' => $asOf->year,
            'barang_tidak_dapat_dihitung' => $unrecognised,
        ];
    }

    private function growthChart(?int $koperasiId, CarbonInterface $start, CarbonInterface $end): array
    {
        $growth = $this->repository->growthByMonth($koperasiId, $start, $end);
        $months = $this->monthKeys($start, $end);

        return $this->chart($months, [
            ['key' => 'barang', 'label' => 'Barang baru', 'data' => $this->integerSeries($months, $growth['barang'])],
            ['key' => 'akun', 'label' => 'Akun baru', 'data' => $this->integerSeries($months, $growth['akun'])],
            ['key' => 'karyawan', 'label' => 'Karyawan masuk', 'data' => $this->integerSeries($months, $growth['karyawan'])],
        ]);
    }

    /** @param array<string, int> $koperasi @param array<string, int> $users */
    private function globalCards(array $koperasi, array $users, ?array $inventory, ?array $employees, ?array $attendance, ?array $payroll): array
    {
        return array_values(array_filter([
            ['key' => 'koperasi_aktif', 'label' => 'Koperasi aktif', 'nilai' => $koperasi['aktif'], 'format' => 'angka'],
            ['key' => 'akun_tenant', 'label' => 'Akun tenant', 'nilai' => $users['total'], 'format' => 'angka'],
            $inventory ? ['key' => 'total_barang', 'label' => 'Total barang', 'nilai' => $inventory['ringkasan']['total_barang'], 'format' => 'angka'] : null,
            $inventory ? ['key' => 'nilai_inventaris', 'label' => 'Nilai inventaris', 'nilai' => $inventory['ringkasan']['total_harga_perolehan'], 'format' => 'rupiah'] : null,
            $employees ? ['key' => 'karyawan_aktif', 'label' => 'Karyawan aktif', 'nilai' => $employees['ringkasan']['aktif'], 'format' => 'angka'] : null,
            $attendance ? ['key' => 'total_absensi', 'label' => 'Pencatatan absensi', 'nilai' => $attendance['ringkasan']['total_pencatatan'], 'format' => 'angka'] : null,
            $payroll ? ['key' => 'total_gaji_bersih', 'label' => 'Total gaji bersih', 'nilai' => $payroll['ringkasan']['total_gaji_bersih'], 'format' => 'rupiah'] : null,
        ]));
    }

    private function koperasiCards(?array $inventory, ?array $employees, ?array $attendance, ?array $payroll): array
    {
        return array_values(array_filter([
            $inventory ? ['key' => 'total_barang', 'label' => 'Total barang', 'nilai' => $inventory['ringkasan']['total_barang'], 'format' => 'angka'] : null,
            $inventory ? ['key' => 'nilai_inventaris', 'label' => 'Nilai inventaris', 'nilai' => $inventory['ringkasan']['total_harga_perolehan'], 'format' => 'rupiah'] : null,
            $employees ? ['key' => 'karyawan_aktif', 'label' => 'Karyawan aktif', 'nilai' => $employees['ringkasan']['aktif'], 'format' => 'angka'] : null,
            $attendance ? ['key' => 'total_absensi', 'label' => 'Pencatatan absensi', 'nilai' => $attendance['ringkasan']['total_pencatatan'], 'format' => 'angka'] : null,
            $payroll ? ['key' => 'total_gaji_bersih', 'label' => 'Total gaji bersih', 'nilai' => $payroll['ringkasan']['total_gaji_bersih'], 'format' => 'rupiah'] : null,
        ]));
    }

    /** @param list<array<string, mixed>> $rows */
    private function normaliseComparison(array $rows): array
    {
        return array_map(fn (array $row) => [
            ...$row,
            'nilai_inventaris' => $this->privacy->money($row['nilai_inventaris']),
            'total_gaji_bersih' => $this->privacy->money($row['total_gaji_bersih']),
        ], $rows);
    }

    /** @param array<string, int> $distribution */
    private function plainDistribution(array $distribution): array
    {
        return collect($distribution)
            ->map(fn (int $total, string $label) => [
                'label' => $label,
                'total' => $total,
                'disembunyikan' => false,
                'pesan' => null,
            ])
            ->values()
            ->all();
    }

    /** @param list<string> $months @param array<string, int> $values @return list<int> */
    private function integerSeries(array $months, array $values): array
    {
        return array_map(fn (string $month) => (int) ($values[$month] ?? 0), $months);
    }

    /**
     * @param  list<string>  $labels
     * @param  list<array<string, mixed>>  $series
     * @return array{labels: list<string>, series: list<array<string, mixed>>}
     */
    private function chart(array $labels, array $series): array
    {
        return ['labels' => $labels, 'series' => $series];
    }

    /** @return list<string> */
    private function monthKeys(CarbonInterface $start, CarbonInterface $end): array
    {
        $months = [];
        $cursor = CarbonImmutable::instance($start)->startOfMonth();
        $last = CarbonImmutable::instance($end)->startOfMonth();

        while ($cursor->lte($last)) {
            $months[] = $cursor->format('Y-m');
            $cursor = $cursor->addMonth();
        }

        return $months;
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    private function previousPeriod(CarbonInterface $start, CarbonInterface $end): array
    {
        $days = $start->diffInDays($end) + 1;
        $previousEnd = CarbonImmutable::instance($start)->subDay();

        return [$previousEnd->subDays($days - 1), $previousEnd];
    }

    private function percentageChange(string $current, string $previous): ?string
    {
        if (bccomp($previous, '0', 2) === 0) {
            return null;
        }

        $difference = bcsub($current, $previous, 4);
        $raw = bcmul(bcdiv($difference, $previous, 6), '100', 6);
        $increment = bccomp($raw, '0', 6) >= 0 ? '0.05' : '-0.05';

        return bcadd($raw, $increment, 1);
    }

    /** @param array<string, mixed> $summary */
    private function normalisePayroll(array $summary): array
    {
        return [
            'total_transaksi' => (int) $summary['total_transaksi'],
            'total_gaji_pokok' => $this->privacy->money($summary['total_gaji_pokok']),
            'total_gaji_bersih' => $this->privacy->money($summary['total_gaji_bersih']),
            'total_tunjangan' => $this->privacy->money($summary['total_tunjangan']),
            'total_potongan' => $this->privacy->money($summary['total_potongan']),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{tanggal_awal: string, tanggal_akhir: string, koperasi_id: ?int, modul: string}
     */
    private function normaliseFilters(array $filters): array
    {
        $today = CarbonImmutable::today(config('app.timezone'));

        return [
            'tanggal_awal' => (string) ($filters['tanggal_awal'] ?? $today->startOfMonth()->subMonths(11)->toDateString()),
            'tanggal_akhir' => (string) ($filters['tanggal_akhir'] ?? $today->toDateString()),
            'koperasi_id' => isset($filters['koperasi_id']) ? (int) $filters['koperasi_id'] : null,
            'modul' => in_array(($filters['modul'] ?? 'semua'), ['semua', 'inventaris', 'kepegawaian', 'absensi', 'penggajian'], true)
                ? (string) ($filters['modul'] ?? 'semua')
                : 'semua',
        ];
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    private function dates(array $filters): array
    {
        return [
            CarbonImmutable::createFromFormat('!Y-m-d', $filters['tanggal_awal']),
            CarbonImmutable::createFromFormat('!Y-m-d', $filters['tanggal_akhir']),
        ];
    }

    private function wants(array $filters, string $module): bool
    {
        return $filters['modul'] === 'semua' || $filters['modul'] === $module;
    }

    private function meta(string $scope, array $filters): array
    {
        return [
            'scope' => $scope,
            'periode' => [
                'tanggalAwal' => $filters['tanggal_awal'],
                'tanggalAkhir' => $filters['tanggal_akhir'],
            ],
            'modul' => $filters['modul'],
            'cacheTtlDetik' => self::CACHE_TTL_SECONDS,
            'minimumCohort' => OwnerAnalyticsPrivacy::MINIMUM_COHORT,
            'definisi' => [
                'akunAktif' => 'Akun tenant terverifikasi (email_verified_at terisi); belum tersedia metrik login terakhir.',
                'inventaris' => 'Snapshot barang dengan tanggal perolehan sampai akhir periode.',
                'nilaiBuku' => 'Proyeksi nilai buku fiskal pada akhir tahun tanggal akhir filter.',
                'karyawanAktif' => 'Belum mengundurkan diri pada tanggal akhir periode.',
                'nilaiUang' => 'Decimal string dua angka desimal; tidak dihitung dengan float.',
            ],
        ];
    }

    private function remember(string $scope, array $filters, callable $callback): array
    {
        return Cache::remember(
            $this->cacheKey($scope, $filters),
            self::CACHE_TTL_SECONDS,
            $callback,
        );
    }
}
