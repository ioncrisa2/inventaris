<?php

namespace App\Repositories;

use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Satu-satunya jalur baca lintas tenant untuk analitik system owner.
 *
 * Repository ini sengaja memakai Query Builder (bukan model operasional),
 * selalu membuang record yatim tanpa koperasi, dan hanya mengembalikan hasil
 * agregat atau metadata control-plane koperasi.
 */
class OwnerAnalyticsRepository
{
    /** @return array{id: int, nama: string}|null */
    public function koperasiMetadata(int $koperasiId): ?array
    {
        $row = DB::table('koperasi')
            ->where('id', $koperasiId)
            ->first(['id', 'nama']);

        return $row ? ['id' => (int) $row->id, 'nama' => (string) $row->nama] : null;
    }

    /** @return list<array{id: int, nama: string}> */
    public function koperasiOptions(): array
    {
        return DB::table('koperasi')
            ->orderBy('nama')
            ->get(['id', 'nama'])
            ->map(fn (object $row) => ['id' => (int) $row->id, 'nama' => (string) $row->nama])
            ->all();
    }

    /**
     * @return array{total: int, aktif: int, nonaktif: int, kedaluwarsa: int, akan_kedaluwarsa: int}
     */
    public function koperasiSummary(CarbonInterface $asOf): array
    {
        $now = $asOf->toDateTimeString();
        $nextThirtyDays = $asOf->copy()->addDays(30)->toDateTimeString();
        $row = DB::table('koperasi')
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM(CASE WHEN is_active = 1 AND (expires_at IS NULL OR expires_at > ?) THEN 1 ELSE 0 END) AS aktif', [$now])
            ->selectRaw('SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) AS nonaktif')
            ->selectRaw('SUM(CASE WHEN is_active = 1 AND expires_at IS NOT NULL AND expires_at <= ? THEN 1 ELSE 0 END) AS kedaluwarsa', [$now])
            ->selectRaw('SUM(CASE WHEN is_active = 1 AND expires_at > ? AND expires_at <= ? THEN 1 ELSE 0 END) AS akan_kedaluwarsa', [$now, $nextThirtyDays])
            ->first();

        return [
            'total' => (int) ($row->total ?? 0),
            'aktif' => (int) ($row->aktif ?? 0),
            'nonaktif' => (int) ($row->nonaktif ?? 0),
            'kedaluwarsa' => (int) ($row->kedaluwarsa ?? 0),
            'akan_kedaluwarsa' => (int) ($row->akan_kedaluwarsa ?? 0),
        ];
    }

    /** @return array{total: int, terverifikasi: int} */
    public function tenantUserSummary(?int $koperasiId = null): array
    {
        $row = $this->tenantQuery('users', $koperasiId)
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM(CASE WHEN email_verified_at IS NOT NULL THEN 1 ELSE 0 END) AS terverifikasi')
            ->first();

        return [
            'total' => (int) ($row->total ?? 0),
            'terverifikasi' => (int) ($row->terverifikasi ?? 0),
        ];
    }

    /**
     * Ringkasan inventaris merupakan snapshot sampai akhir periode, bukan
     * daftar barang yang dibuat di dalam periode.
     *
     * @return array{total_barang: int, total_harga_perolehan: string}
     */
    public function inventorySummary(?int $koperasiId, CarbonInterface $asOf): array
    {
        $row = $this->tenantQuery('barang', $koperasiId)
            ->where('barang.tanggal_perolehan', '<=', $asOf->toDateString())
            ->selectRaw('COUNT(*) AS total_barang')
            ->selectRaw('COALESCE(SUM(barang.harga_perolehan), 0) AS total_harga_perolehan')
            ->first();

        return [
            'total_barang' => (int) ($row->total_barang ?? 0),
            'total_harga_perolehan' => (string) ($row->total_harga_perolehan ?? '0'),
        ];
    }

    /**
     * Harga perolehan dikelompokkan per kategori dan bulan. Hasil ini tetap
     * agregat tetapi cukup untuk memakai ulang kalkulator penyusutan bulanan.
     *
     * @return list<array{kategori: string, bulan_perolehan: string, total_barang: int, harga_perolehan: string}>
     */
    public function inventoryDepreciationCohorts(?int $koperasiId, CarbonInterface $asOf): array
    {
        $monthExpression = $this->monthExpression('barang.tanggal_perolehan');

        return $this->tenantQuery('barang', $koperasiId)
            ->where('barang.tanggal_perolehan', '<=', $asOf->toDateString())
            ->select('barang.kategori')
            ->selectRaw("{$monthExpression} AS bulan_perolehan")
            ->selectRaw('COUNT(*) AS total_barang')
            ->selectRaw('COALESCE(SUM(barang.harga_perolehan), 0) AS harga_perolehan')
            ->groupBy('barang.kategori')
            ->groupByRaw($monthExpression)
            ->orderByRaw($monthExpression)
            ->get()
            ->map(fn (object $row) => [
                'kategori' => (string) $row->kategori,
                'bulan_perolehan' => (string) $row->bulan_perolehan,
                'total_barang' => (int) $row->total_barang,
                'harga_perolehan' => (string) $row->harga_perolehan,
            ])
            ->all();
    }

    /** @return array<string, int> */
    public function inventoryConditionDistribution(?int $koperasiId, CarbonInterface $asOf): array
    {
        $latestDate = DB::table('riwayat_kondisi_barang')
            ->where('tanggal_pemeriksaan', '<=', $asOf->toDateString())
            ->select('barang_id')
            ->selectRaw('MAX(tanggal_pemeriksaan) AS tanggal_terakhir')
            ->groupBy('barang_id');

        $latestRow = DB::table('riwayat_kondisi_barang AS riwayat')
            ->joinSub($latestDate, 'tanggal_terakhir', function ($join) {
                $join->on('tanggal_terakhir.barang_id', '=', 'riwayat.barang_id')
                    ->on('tanggal_terakhir.tanggal_terakhir', '=', 'riwayat.tanggal_pemeriksaan');
            })
            ->select('riwayat.barang_id')
            ->selectRaw('MAX(riwayat.id) AS riwayat_id')
            ->groupBy('riwayat.barang_id');

        return $this->tenantQuery('barang', $koperasiId)
            ->where('barang.tanggal_perolehan', '<=', $asOf->toDateString())
            ->leftJoinSub($latestRow, 'baris_terakhir', 'baris_terakhir.barang_id', '=', 'barang.id')
            ->leftJoin('riwayat_kondisi_barang AS kondisi_terakhir', 'kondisi_terakhir.id', '=', 'baris_terakhir.riwayat_id')
            ->selectRaw("COALESCE(kondisi_terakhir.kondisi, 'Belum diperiksa') AS label")
            ->selectRaw('COUNT(*) AS total')
            ->groupBy('kondisi_terakhir.kondisi')
            ->orderBy('label')
            ->pluck('total', 'label')
            ->map(fn (mixed $total) => (int) $total)
            ->all();
    }

    /** @return array<string, int> */
    public function inventoryDimensionDistribution(string $dimension, ?int $koperasiId, CarbonInterface $asOf): array
    {
        if (! in_array($dimension, ['kategori', 'jenis_barang'], true)) {
            throw new \InvalidArgumentException('Dimensi inventaris tidak didukung.');
        }

        return $this->tenantQuery('barang', $koperasiId)
            ->where('barang.tanggal_perolehan', '<=', $asOf->toDateString())
            ->selectRaw("COALESCE(NULLIF(barang.{$dimension}, ''), 'Tidak ditentukan') AS label")
            ->selectRaw('COUNT(*) AS total')
            ->groupBy("barang.{$dimension}")
            ->orderBy('label')
            ->pluck('total', 'label')
            ->map(fn (mixed $total) => (int) $total)
            ->all();
    }

    /**
     * @return array{belum_diperiksa: int, tanpa_foto: int, tanpa_nota: int}
     */
    public function inventoryCompleteness(?int $koperasiId, CarbonInterface $asOf): array
    {
        $row = $this->tenantQuery('barang', $koperasiId)
            ->where('barang.tanggal_perolehan', '<=', $asOf->toDateString())
            ->selectRaw('SUM(CASE WHEN NOT EXISTS (SELECT 1 FROM riwayat_kondisi_barang WHERE riwayat_kondisi_barang.barang_id = barang.id AND riwayat_kondisi_barang.tanggal_pemeriksaan <= ?) THEN 1 ELSE 0 END) AS belum_diperiksa', [$asOf->toDateString()])
            ->selectRaw("SUM(CASE WHEN barang.foto_sampul IS NULL OR barang.foto_sampul = '' THEN 1 ELSE 0 END) AS tanpa_foto")
            ->selectRaw('SUM(CASE WHEN NOT EXISTS (SELECT 1 FROM dokumen_barang WHERE dokumen_barang.barang_id = barang.id AND dokumen_barang.jenis_dokumen = ?) THEN 1 ELSE 0 END) AS tanpa_nota', ['Nota Pembelian'])
            ->first();

        return [
            'belum_diperiksa' => (int) ($row->belum_diperiksa ?? 0),
            'tanpa_foto' => (int) ($row->tanpa_foto ?? 0),
            'tanpa_nota' => (int) ($row->tanpa_nota ?? 0),
        ];
    }

    /** @return array{total: int, aktif: int, nonaktif: int} */
    public function employeeSummary(?int $koperasiId, CarbonInterface $asOf): array
    {
        $query = $this->employeesAsOf($koperasiId, $asOf);
        $row = $query
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM(CASE WHEN tanggal_mengundurkan_diri IS NULL OR tanggal_mengundurkan_diri > ? THEN 1 ELSE 0 END) AS aktif', [$asOf->toDateString()])
            ->selectRaw('SUM(CASE WHEN tanggal_mengundurkan_diri IS NOT NULL AND tanggal_mengundurkan_diri <= ? THEN 1 ELSE 0 END) AS nonaktif', [$asOf->toDateString()])
            ->first();

        return [
            'total' => (int) ($row->total ?? 0),
            'aktif' => (int) ($row->aktif ?? 0),
            'nonaktif' => (int) ($row->nonaktif ?? 0),
        ];
    }

    /** @return array<string, int> */
    public function employeeDistribution(string $dimension, ?int $koperasiId, CarbonInterface $asOf): array
    {
        $query = $this->employeesAsOf($koperasiId, $asOf)
            ->where(function (Builder $query) use ($asOf) {
                $query->whereNull('karyawan.tanggal_mengundurkan_diri')
                    ->orWhere('karyawan.tanggal_mengundurkan_diri', '>', $asOf->toDateString());
            });

        if ($dimension === 'unit_kerja') {
            return $query
                ->join('unit_kerja', 'unit_kerja.id', '=', 'karyawan.unit_kerja_id')
                ->selectRaw("COALESCE(NULLIF(unit_kerja.nama_unit, ''), 'Tidak ditentukan') AS label")
                ->selectRaw('COUNT(*) AS total')
                ->groupBy('unit_kerja.nama_unit')
                ->orderBy('label')
                ->pluck('total', 'label')
                ->map(fn (mixed $total) => (int) $total)
                ->all();
        }

        if ($dimension !== 'status_karyawan') {
            throw new \InvalidArgumentException('Dimensi kepegawaian tidak didukung.');
        }

        return $query
            ->selectRaw("COALESCE(NULLIF(karyawan.status_karyawan, ''), 'Tidak ditentukan') AS label")
            ->selectRaw('COUNT(*) AS total')
            ->groupBy('karyawan.status_karyawan')
            ->orderBy('label')
            ->pluck('total', 'label')
            ->map(fn (mixed $total) => (int) $total)
            ->all();
    }

    /** @return array{total_aktif: int, lengkap: int} */
    public function employeeCompleteness(?int $koperasiId, CarbonInterface $asOf): array
    {
        $row = $this->employeesAsOf($koperasiId, $asOf)
            ->where(function (Builder $query) use ($asOf) {
                $query->whereNull('karyawan.tanggal_mengundurkan_diri')
                    ->orWhere('karyawan.tanggal_mengundurkan_diri', '>', $asOf->toDateString());
            })
            ->selectRaw('COUNT(*) AS total_aktif')
            ->selectRaw("SUM(CASE WHEN karyawan.tanggal_masuk_kerja IS NOT NULL AND karyawan.foto_karyawan IS NOT NULL AND karyawan.foto_karyawan <> '' AND karyawan.nomor_ktp IS NOT NULL AND karyawan.nomor_ktp <> '' AND EXISTS (SELECT 1 FROM dokumen_karyawan WHERE dokumen_karyawan.karyawan_id = karyawan.id AND dokumen_karyawan.jenis_dokumen = 'KTP') THEN 1 ELSE 0 END) AS lengkap")
            ->first();

        return [
            'total_aktif' => (int) ($row->total_aktif ?? 0),
            'lengkap' => (int) ($row->lengkap ?? 0),
        ];
    }

    /**
     * @return array{masuk: array<string, int>, keluar: array<string, int>}
     */
    public function employeeMovementTrend(?int $koperasiId, CarbonInterface $start, CarbonInterface $end): array
    {
        return [
            'masuk' => $this->monthlyCount(
                $this->tenantQuery('karyawan', $koperasiId),
                'karyawan.tanggal_masuk_kerja',
                $start,
                $end,
            ),
            'keluar' => $this->monthlyCount(
                $this->tenantQuery('karyawan', $koperasiId),
                'karyawan.tanggal_mengundurkan_diri',
                $start,
                $end,
            ),
        ];
    }

    /** @return array<string, array<string, int>> */
    public function attendanceByMonth(?int $koperasiId, CarbonInterface $start, CarbonInterface $end): array
    {
        $monthExpression = $this->monthExpression('absensi.tanggal');

        $rows = $this->tenantQuery('absensi', $koperasiId)
            ->whereBetween('absensi.tanggal', [$start->toDateString(), $end->toDateString()])
            ->selectRaw("{$monthExpression} AS bulan")
            ->addSelect('absensi.status')
            ->selectRaw('COUNT(*) AS total')
            ->groupByRaw($monthExpression)
            ->groupBy('absensi.status')
            ->orderByRaw($monthExpression)
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $result[(string) $row->bulan][(string) $row->status] = (int) $row->total;
        }

        return $result;
    }

    /** @return array{total: int, per_status: array<string, int>, karyawan_tercatat: int} */
    public function attendanceSummary(?int $koperasiId, CarbonInterface $start, CarbonInterface $end): array
    {
        $query = $this->tenantQuery('absensi', $koperasiId)
            ->whereBetween('absensi.tanggal', [$start->toDateString(), $end->toDateString()]);
        $perStatus = (clone $query)
            ->select('absensi.status')
            ->selectRaw('COUNT(*) AS total')
            ->groupBy('absensi.status')
            ->pluck('total', 'status')
            ->map(fn (mixed $total) => (int) $total)
            ->all();

        return [
            'total' => array_sum($perStatus),
            'per_status' => $perStatus,
            'karyawan_tercatat' => (clone $query)->distinct()->count('absensi.karyawan_id'),
        ];
    }

    /**
     * @return array{total_transaksi: int, total_gaji_pokok: string, total_gaji_bersih: string, total_tunjangan: string, total_potongan: string}
     */
    public function payrollSummary(?int $koperasiId, CarbonInterface $start, CarbonInterface $end): array
    {
        $query = $this->payrollPeriodQuery(
            $this->tenantQuery('transaksi_gaji', $koperasiId),
            $start,
            $end,
        );
        $row = (clone $query)
            ->selectRaw('COUNT(*) AS total_transaksi')
            ->selectRaw('COALESCE(SUM(transaksi_gaji.gaji_pokok), 0) AS total_gaji_pokok')
            ->selectRaw('COALESCE(SUM(transaksi_gaji.gaji_bersih), 0) AS total_gaji_bersih')
            ->first();
        $details = DB::table('transaksi_gaji_detail')
            ->joinSub((clone $query)->select('transaksi_gaji.id'), 'gaji', 'gaji.id', '=', 'transaksi_gaji_detail.transaksi_gaji_id')
            ->selectRaw("COALESCE(SUM(CASE WHEN transaksi_gaji_detail.jenis_snapshot = 'Tunjangan' THEN transaksi_gaji_detail.nominal_hasil ELSE 0 END), 0) AS total_tunjangan")
            ->selectRaw("COALESCE(SUM(CASE WHEN transaksi_gaji_detail.jenis_snapshot = 'Potongan' THEN transaksi_gaji_detail.nominal_hasil ELSE 0 END), 0) AS total_potongan")
            ->first();

        return [
            'total_transaksi' => (int) ($row->total_transaksi ?? 0),
            'total_gaji_pokok' => (string) ($row->total_gaji_pokok ?? '0'),
            'total_gaji_bersih' => (string) ($row->total_gaji_bersih ?? '0'),
            'total_tunjangan' => (string) ($details->total_tunjangan ?? '0'),
            'total_potongan' => (string) ($details->total_potongan ?? '0'),
        ];
    }

    /**
     * @return array<string, array{total_transaksi: int, total_gaji_bersih: string}>
     */
    public function payrollByMonth(?int $koperasiId, CarbonInterface $start, CarbonInterface $end): array
    {
        $rows = $this->payrollPeriodQuery(
            $this->tenantQuery('transaksi_gaji', $koperasiId),
            $start,
            $end,
        )
            ->select('transaksi_gaji.tahun', 'transaksi_gaji.bulan')
            ->selectRaw('COUNT(*) AS total_transaksi')
            ->selectRaw('COALESCE(SUM(transaksi_gaji.gaji_bersih), 0) AS total_gaji_bersih')
            ->groupBy('transaksi_gaji.tahun', 'transaksi_gaji.bulan')
            ->orderBy('transaksi_gaji.tahun')
            ->orderBy('transaksi_gaji.bulan')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $key = sprintf('%04d-%02d', $row->tahun, $row->bulan);
            $result[$key] = [
                'total_transaksi' => (int) $row->total_transaksi,
                'total_gaji_bersih' => (string) $row->total_gaji_bersih,
            ];
        }

        return $result;
    }

    /**
     * Pertumbuhan jumlah record baru. Nilai karyawan memakai tanggal masuk,
     * barang memakai tanggal perolehan, dan akun memakai created_at.
     *
     * @return array{barang: array<string, int>, akun: array<string, int>, karyawan: array<string, int>}
     */
    public function growthByMonth(?int $koperasiId, CarbonInterface $start, CarbonInterface $end): array
    {
        return [
            'barang' => $this->monthlyCount($this->tenantQuery('barang', $koperasiId), 'barang.tanggal_perolehan', $start, $end),
            'akun' => $this->monthlyCount($this->tenantQuery('users', $koperasiId), 'users.created_at', $start, $end),
            'karyawan' => $this->monthlyCount($this->tenantQuery('karyawan', $koperasiId), 'karyawan.tanggal_masuk_kerja', $start, $end),
        ];
    }

    /**
     * Perbandingan control-plane; `id` di sini adalah ID koperasi, bukan ID
     * record operasional, dan diperlukan untuk route filter ringkasan.
     *
     * @return list<array{id: int, nama: string, total_barang: int, nilai_inventaris: string, karyawan_aktif: int, total_absensi: int, total_gaji_bersih: string}>
     */
    public function cooperativeComparison(CarbonInterface $start, CarbonInterface $end): array
    {
        $koperasi = DB::table('koperasi')->orderBy('nama')->get(['id', 'nama']);
        $barang = $this->aggregateByKoperasi(
            DB::table('barang')->whereNotNull('koperasi_id')->where('tanggal_perolehan', '<=', $end->toDateString()),
            'COUNT(*) AS total_barang, COALESCE(SUM(harga_perolehan), 0) AS nilai_inventaris',
        );
        $karyawan = $this->aggregateByKoperasi(
            DB::table('karyawan')->whereNotNull('koperasi_id')
                ->where(function (Builder $query) use ($end) {
                    $query->whereNull('tanggal_mengundurkan_diri')
                        ->orWhere('tanggal_mengundurkan_diri', '>', $end->toDateString());
                }),
            'COUNT(*) AS karyawan_aktif',
        );
        $absensi = $this->aggregateByKoperasi(
            DB::table('absensi')->whereNotNull('koperasi_id')->whereBetween('tanggal', [$start->toDateString(), $end->toDateString()]),
            'COUNT(*) AS total_absensi',
        );
        $gaji = $this->aggregateByKoperasi(
            $this->payrollPeriodQuery(DB::table('transaksi_gaji')->whereNotNull('koperasi_id'), $start, $end),
            'COALESCE(SUM(gaji_bersih), 0) AS total_gaji_bersih',
        );

        return $koperasi->map(function (object $row) use ($barang, $karyawan, $absensi, $gaji) {
            $id = (int) $row->id;

            return [
                'id' => $id,
                'nama' => (string) $row->nama,
                'total_barang' => (int) ($barang[$id]->total_barang ?? 0),
                'nilai_inventaris' => (string) ($barang[$id]->nilai_inventaris ?? '0'),
                'karyawan_aktif' => (int) ($karyawan[$id]->karyawan_aktif ?? 0),
                'total_absensi' => (int) ($absensi[$id]->total_absensi ?? 0),
                'total_gaji_bersih' => (string) ($gaji[$id]->total_gaji_bersih ?? '0'),
            ];
        })->all();
    }

    private function tenantQuery(string $table, ?int $koperasiId): Builder
    {
        return DB::table($table)
            ->whereNotNull("{$table}.koperasi_id")
            ->when($koperasiId !== null, fn (Builder $query) => $query->where("{$table}.koperasi_id", $koperasiId));
    }

    private function employeesAsOf(?int $koperasiId, CarbonInterface $asOf): Builder
    {
        return $this->tenantQuery('karyawan', $koperasiId)
            ->where(function (Builder $query) use ($asOf) {
                $query->whereNull('karyawan.tanggal_masuk_kerja')
                    ->orWhere('karyawan.tanggal_masuk_kerja', '<=', $asOf->toDateString());
            });
    }

    private function payrollPeriodQuery(Builder $query, CarbonInterface $start, CarbonInterface $end): Builder
    {
        $startKey = ($start->year * 100) + $start->month;
        $endKey = ($end->year * 100) + $end->month;

        return $query->whereRaw('(transaksi_gaji.tahun * 100 + transaksi_gaji.bulan) BETWEEN ? AND ?', [$startKey, $endKey]);
    }

    /** @return array<string, int> */
    private function monthlyCount(Builder $query, string $dateColumn, CarbonInterface $start, CarbonInterface $end): array
    {
        $monthExpression = $this->monthExpression($dateColumn);

        $query->whereNotNull($dateColumn);
        if (str_ends_with($dateColumn, '.created_at')) {
            $query->where($dateColumn, '>=', $start->copy()->startOfDay()->toDateTimeString())
                ->where($dateColumn, '<', $end->copy()->addDay()->startOfDay()->toDateTimeString());
        } else {
            $query->whereBetween($dateColumn, [$start->toDateString(), $end->toDateString()]);
        }

        return $query
            ->selectRaw("{$monthExpression} AS bulan")
            ->selectRaw('COUNT(*) AS total')
            ->groupByRaw($monthExpression)
            ->orderByRaw($monthExpression)
            ->pluck('total', 'bulan')
            ->map(fn (mixed $total) => (int) $total)
            ->all();
    }

    /** @return array<int, object> */
    private function aggregateByKoperasi(Builder $query, string $aggregateSql): array
    {
        return $query
            ->select('koperasi_id')
            ->selectRaw($aggregateSql)
            ->groupBy('koperasi_id')
            ->get()
            ->keyBy(fn (object $row) => (int) $row->koperasi_id)
            ->all();
    }

    private function monthExpression(string $qualifiedColumn): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "strftime('%Y-%m', {$qualifiedColumn})",
            'pgsql' => "to_char({$qualifiedColumn}, 'YYYY-MM')",
            'sqlsrv' => "FORMAT({$qualifiedColumn}, 'yyyy-MM')",
            default => "DATE_FORMAT({$qualifiedColumn}, '%Y-%m')",
        };
    }
}
