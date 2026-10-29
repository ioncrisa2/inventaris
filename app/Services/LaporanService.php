<?php

namespace App\Services;

use App\Models\Barang;
use App\Repositories\KaryawanRepository;
use App\Repositories\LaporanRepository;
use App\Repositories\UnitKerjaRepository;
use App\Support\PenyusutanCalculator;
use App\Support\PerPage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class LaporanService
{
    public function __construct(
        private LaporanRepository $laporanRepository,
        private UnitKerjaRepository $unitKerjaRepository,
        private KaryawanRepository $karyawanRepository,
    ) {}

    public function inventaris(array $filters, int $perPage = PerPage::DEFAULT): array
    {
        $query = $this->laporanRepository->inventarisQuery($filters);
        $semuaBarang = (clone $query)->get();
        $ringkasan = $this->ringkasanNilaiInventaris($semuaBarang);
        $rekapKategori = $this->rekapKategoriInventaris($semuaBarang);

        $sort = $filters['sort'] ?? 'kode_barang';
        $direction = strtolower($filters['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        $sorted = $semuaBarang->sortBy(function (Barang $barang) use ($sort) {
            return match ($sort) {
                'nama_barang' => strtolower($barang->nama_barang),
                'jenis_barang' => strtolower($barang->jenis_barang ?? ''),
                'kategori' => $barang->kategori,
                'unit_kerja' => strtolower($barang->unitKerja?->nama_unit ?? ''),
                'tanggal_perolehan' => $barang->tanggal_perolehan?->timestamp ?? 0,
                'kondisi' => strtolower($barang->kondisiTerakhir?->kondisi ?? ''),
                'nilai_buku' => (float) $barang->nilaiBukuTerakhir(),
                default => $barang->kode_barang,
            };
        }, SORT_REGULAR, $direction === 'desc')->values();

        $page = (int) request('page', 1);
        $items = $sorted->slice(($page - 1) * $perPage, $perPage)->values();

        $barangs = new \Illuminate\Pagination\LengthAwarePaginator(
            $items,
            $sorted->count(),
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );

        return [
            'barangs' => $barangs,
            'totalBarang' => $barangs->total(),
            'totalNilai' => $ringkasan['totalNilai'],
            'barangPerluPerbaikan' => $this->laporanRepository->barangPerluPerbaikan($query),
            'rekapKategori' => $rekapKategori,
            'totalBarangTerekap' => $rekapKategori->sum('total_barang'),
            'unitKerjas' => isset($filters['koperasi_id'])
                ? $this->unitKerjaRepository->orderedList((int) $filters['koperasi_id'])
                : $this->unitKerjaRepository->orderedList(),
        ];
    }

    public function inventarisCetak(array $filters): array
    {
        $query = $this->laporanRepository->inventarisQuery($filters);
        $semuaBarang = (clone $query)->get();
        $ringkasan = $this->ringkasanNilaiInventaris($semuaBarang);
        $rekapKategori = $this->rekapKategoriInventaris($semuaBarang);

        $sort = $filters['sort'] ?? 'kode_barang';
        $direction = strtolower($filters['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        $sorted = $semuaBarang->sortBy(function (Barang $barang) use ($sort) {
            return match ($sort) {
                'nama_barang' => strtolower($barang->nama_barang),
                'jenis_barang' => strtolower($barang->jenis_barang ?? ''),
                'kategori' => $barang->kategori,
                'unit_kerja' => strtolower($barang->unitKerja?->nama_unit ?? ''),
                'tanggal_perolehan' => $barang->tanggal_perolehan?->timestamp ?? 0,
                'kondisi' => strtolower($barang->kondisiTerakhir?->kondisi ?? ''),
                'nilai_buku' => (float) $barang->nilaiBukuTerakhir(),
                default => $barang->kode_barang,
            };
        }, SORT_REGULAR, $direction === 'desc')->values();

        return [
            'barangs' => $sorted,
            ...$ringkasan,
            'barangPerluPerbaikan' => $this->laporanRepository->barangPerluPerbaikan($query),
            'rekapKategori' => $rekapKategori,
            'totalBarangTerekap' => $rekapKategori->sum('total_barang'),
            'selectedUnitKerja' => $this->selectedUnitKerja($filters['unit_kerja_id'] ?? null),
        ];
    }

    public function inventarisExportRows(array $filters): Builder
    {
        $sort = $filters['sort'] ?? 'kode_barang';
        $direction = strtolower($filters['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
        $dbColumn = in_array($sort, ['kode_barang', 'nama_barang', 'jenis_barang', 'kategori', 'tanggal_perolehan'], true) ? $sort : 'kode_barang';

        return $this->laporanRepository->inventarisQuery($filters)
            ->orderBy($dbColumn, $direction);
    }

    public function absensi(array $filters, int $bulan, int $tahun, int $perPage = PerPage::DEFAULT): array
    {
        $filtersWithoutStatus = Arr::except($filters, ['status']);
        $baseQuery = $this->laporanRepository->absensiQuery($filtersWithoutStatus, $bulan, $tahun);
        $stats = $this->laporanRepository->totalPerStatusAbsensi($baseQuery);

        $filteredQuery = $this->laporanRepository->absensiQuery($filters, $bulan, $tahun);
        $modeTotals = $this->laporanRepository->totalPerStatusAbsensi($filteredQuery);
        $totalHariFiltered = $modeTotals['totalHadir'] + $modeTotals['totalIzin'] + $modeTotals['totalSakit'] + $modeTotals['totalCuti'] + $modeTotals['totalDinas'] + $modeTotals['totalAlpha'];
        $modeTotals['totalHari'] = $totalHariFiltered;

        $mode = $filters['mode'] ?? 'karyawan';
        $sort = $filters['sort'] ?? ($mode === 'karyawan' ? 'karyawan' : 'tanggal');
        $direction = strtolower($filters['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        if ($mode === 'karyawan') {
            $karyawanQuery = $this->laporanRepository->absensiPerKaryawanQuery($filteredQuery);

            if ($sort === 'unit_kerja') {
                $karyawanQuery->orderBy('unit_kerja.nama_unit', $direction);
            } elseif ($sort === 'nik') {
                $karyawanQuery->orderBy('karyawan.nik', $direction);
            } elseif (in_array($sort, ['hadir', 'izin', 'sakit', 'cuti', 'dinas', 'alpha', 'total_hari'])) {
                $karyawanQuery->orderBy($sort, $direction);
            } else {
                $karyawanQuery->orderBy('karyawan.nama_lengkap', $direction);
            }

            $paginator = $karyawanQuery->paginate($perPage)->withQueryString();
        } else {
            $tanggalQuery = clone $filteredQuery;

            if ($sort === 'karyawan') {
                $tanggalQuery->join('karyawan', 'karyawan.id', '=', 'absensi.karyawan_id')
                    ->orderBy('karyawan.nama_lengkap', $direction)
                    ->select('absensi.*');
            } elseif ($sort === 'unit_kerja') {
                $tanggalQuery->join('karyawan', 'karyawan.id', '=', 'absensi.karyawan_id')
                    ->leftJoin('unit_kerja', 'unit_kerja.id', '=', 'karyawan.unit_kerja_id')
                    ->orderBy('unit_kerja.nama_unit', $direction)
                    ->select('absensi.*');
            } elseif (in_array($sort, ['status', 'jam_masuk', 'jam_keluar'])) {
                $tanggalQuery->orderBy($sort, $direction);
            } else {
                $tanggalQuery->orderBy('tanggal', $direction)->orderBy('karyawan_id');
            }

            $paginator = $tanggalQuery->paginate($perPage)->withQueryString();
        }

        return [
            'paginator' => $paginator,
            'absensis' => $paginator, // Kompatibilitas backwards view/test lama
            'mode' => $mode,
            'tipePeriode' => $filters['tipe_periode'] ?? 'kalender',
            'status' => $filters['status'] ?? null,
            'totalRecords' => (clone $filteredQuery)->count(),
            'modeTotals' => $modeTotals,
            'unitKerjas' => isset($filters['koperasi_id'])
                ? $this->unitKerjaRepository->orderedList((int) $filters['koperasi_id'])
                : $this->unitKerjaRepository->orderedList(),
            'karyawans' => isset($filters['koperasi_id'])
                ? $this->karyawanRepository->orderedList((int) $filters['koperasi_id'])
                : $this->karyawanRepository->orderedList(),
            'selectedKaryawanId' => $filters['karyawan_id'] ?? null,
            'selectedKaryawan' => $this->selectedKaryawan($filters['karyawan_id'] ?? null),
            ...$stats,
        ];
    }

    public function absensiCetak(array $filters, int $bulan, int $tahun): array
    {
        $filteredQuery = $this->laporanRepository->absensiQuery($filters, $bulan, $tahun);
        $mode = $filters['mode'] ?? 'karyawan';

        $modeTotals = $this->laporanRepository->totalPerStatusAbsensi($filteredQuery);
        $totalHariFiltered = $modeTotals['totalHadir'] + $modeTotals['totalIzin'] + $modeTotals['totalSakit'] + $modeTotals['totalCuti'] + $modeTotals['totalDinas'] + $modeTotals['totalAlpha'];
        $modeTotals['totalHari'] = $totalHariFiltered;

        $karyawanRows = $this->laporanRepository->absensiPerKaryawanQuery($filteredQuery)
            ->orderBy('karyawan.nama_lengkap')
            ->get();

        $absensis = (clone $filteredQuery)->orderBy('tanggal')->orderBy('karyawan_id')->get();

        return [
            'mode' => $mode,
            'tipePeriode' => $filters['tipe_periode'] ?? 'kalender',
            'karyawanRows' => $karyawanRows,
            'absensis' => $absensis,
            'selectedKaryawan' => $this->selectedKaryawan($filters['karyawan_id'] ?? null),
            'modeTotals' => $modeTotals,
            ...$modeTotals,
        ];
    }

    public function absensiExportRows(array $filters, int $bulan, int $tahun): Builder
    {
        return $this->laporanRepository->absensiQuery($filters, $bulan, $tahun)
            ->orderBy('tanggal')
            ->orderBy('karyawan_id');
    }

    public function kepegawaian(array $filters, int $perPage = PerPage::DEFAULT): array
    {
        $query = $this->laporanRepository->kepegawaianQuery($filters);
        $ringkasan = $this->laporanRepository->ringkasanKepegawaian($query);

        $sort = $filters['sort'] ?? 'nik';
        $direction = strtolower($filters['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        $sortedQuery = clone $query;
        if ($sort === 'unit_kerja') {
            $sortedQuery->leftJoin('unit_kerja', 'unit_kerja.id', '=', 'karyawan.unit_kerja_id')
                ->select('karyawan.*')
                ->orderBy('unit_kerja.nama_unit', $direction);
        } elseif ($sort === 'keaktifan') {
            $sortedQuery->orderByRaw('CASE WHEN tanggal_mengundurkan_diri IS NULL THEN 0 ELSE 1 END ' . $direction);
        } elseif (in_array($sort, ['nik', 'nama_lengkap', 'jabatan', 'status_karyawan', 'gaji_pokok'])) {
            $sortedQuery->orderBy($sort, $direction);
        } else {
            $sortedQuery->orderBy('nik', 'asc');
        }

        $karyawans = $sortedQuery->paginate($perPage)->withQueryString();

        return [
            'karyawans' => $karyawans,
            'totalKaryawan' => $karyawans->total(),
            'totalAktif' => $ringkasan['totalAktif'],
            'totalMengundurkanDiri' => $ringkasan['totalMengundurkanDiri'],
            'totalGajiAktif' => $ringkasan['totalGajiAktif'],
            'totalGajiSemua' => $ringkasan['totalGajiSemua'],
            'rekapStatus' => $this->laporanRepository->rekapStatusKepegawaian($query),
            'rekapUnitKerja' => $this->laporanRepository->rekapUnitKerjaKepegawaian($query),
            'unitKerjas' => isset($filters['koperasi_id'])
                ? $this->unitKerjaRepository->orderedList((int) $filters['koperasi_id'])
                : $this->unitKerjaRepository->orderedList(),
        ];
    }

    public function kepegawaianCetak(array $filters): array
    {
        $query = $this->laporanRepository->kepegawaianQuery($filters);
        $ringkasan = $this->laporanRepository->ringkasanKepegawaian($query);

        $sort = $filters['sort'] ?? 'nik';
        $direction = strtolower($filters['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        $sortedQuery = clone $query;
        if ($sort === 'unit_kerja') {
            $sortedQuery->leftJoin('unit_kerja', 'unit_kerja.id', '=', 'karyawan.unit_kerja_id')
                ->select('karyawan.*')
                ->orderBy('unit_kerja.nama_unit', $direction);
        } elseif ($sort === 'keaktifan') {
            $sortedQuery->orderByRaw('CASE WHEN tanggal_mengundurkan_diri IS NULL THEN 0 ELSE 1 END ' . $direction);
        } elseif (in_array($sort, ['nik', 'nama_lengkap', 'jabatan', 'status_karyawan', 'gaji_pokok'])) {
            $sortedQuery->orderBy($sort, $direction);
        } else {
            $sortedQuery->orderBy('nik', 'asc');
        }

        return [
            'karyawans' => $sortedQuery->get(),
            ...$ringkasan,
            'rekapStatus' => $this->laporanRepository->rekapStatusKepegawaian($query),
            'rekapUnitKerja' => $this->laporanRepository->rekapUnitKerjaKepegawaian($query),
            'selectedUnitKerja' => $this->selectedUnitKerja($filters['unit_kerja_id'] ?? null),
        ];
    }

    public function kepegawaianExportRows(array $filters): Builder
    {
        $query = $this->laporanRepository->kepegawaianQuery($filters);
        $sort = $filters['sort'] ?? 'nik';
        $direction = strtolower($filters['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        if ($sort === 'unit_kerja') {
            return $query->leftJoin('unit_kerja', 'unit_kerja.id', '=', 'karyawan.unit_kerja_id')
                ->select('karyawan.*')
                ->orderBy('unit_kerja.nama_unit', $direction);
        } elseif ($sort === 'keaktifan') {
            return $query->orderByRaw('CASE WHEN tanggal_mengundurkan_diri IS NULL THEN 0 ELSE 1 END ' . $direction);
        } elseif (in_array($sort, ['nik', 'nama_lengkap', 'jabatan', 'status_karyawan', 'gaji_pokok'])) {
            return $query->orderBy($sort, $direction);
        }

        return $query->orderBy('nik', 'asc');
    }

    public function penggajian(array $filters, int $bulan, int $tahun, int $perPage = PerPage::DEFAULT): array
    {
        $query = $this->laporanRepository->penggajianQuery($filters, $bulan, $tahun);
        [$totalTunjangan, $totalPotongan] = $this->laporanRepository->totalTunjanganPotongan($query);
        $ringkasan = $this->laporanRepository->ringkasanPenggajian($query);

        $sort = $filters['sort'] ?? 'karyawan';
        $direction = strtolower($filters['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        $withCols = $this->laporanRepository->withTunjanganPotongan($query);

        if ($sort === 'unit_kerja') {
            $withCols->join('karyawan', 'karyawan.id', '=', 'transaksi_gaji.karyawan_id')
                ->leftJoin('unit_kerja', 'unit_kerja.id', '=', 'karyawan.unit_kerja_id')
                ->orderBy('unit_kerja.nama_unit', $direction);
        } elseif (in_array($sort, ['gaji_pokok', 'gaji_bersih', 'tunjangan', 'potongan'])) {
            $withCols->orderBy($sort, $direction);
        } else {
            // Default: nama karyawan ASC
            $withCols->join('karyawan', 'karyawan.id', '=', 'transaksi_gaji.karyawan_id')
                ->orderBy('karyawan.nama_lengkap', $direction);
        }

        $transaksiGaji = $withCols->paginate($perPage)->withQueryString();

        return [
            'transaksiGaji' => $transaksiGaji,
            'totalTransaksi' => $transaksiGaji->total(),
            'totalGajiPokok' => $ringkasan['totalGajiPokok'],
            'totalTunjangan' => $totalTunjangan,
            'totalPotongan' => $totalPotongan,
            'totalGajiBersih' => $ringkasan['totalGajiBersih'],
            'rekapUnitKerja' => $this->laporanRepository->rekapUnitKerjaPenggajian($query),
            'unitKerjas' => isset($filters['koperasi_id'])
                ? $this->unitKerjaRepository->orderedList((int) $filters['koperasi_id'])
                : $this->unitKerjaRepository->orderedList(),
        ];
    }

    public function penggajianCetak(array $filters, int $bulan, int $tahun): array
    {
        $query = $this->laporanRepository->penggajianQuery($filters, $bulan, $tahun);
        [$totalTunjangan, $totalPotongan] = $this->laporanRepository->totalTunjanganPotongan($query);
        $ringkasan = $this->laporanRepository->ringkasanPenggajian($query);

        $sort = $filters['sort'] ?? 'karyawan';
        $direction = strtolower($filters['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        $withCols = $this->laporanRepository->withTunjanganPotongan($query);

        if ($sort === 'unit_kerja') {
            $withCols->join('karyawan', 'karyawan.id', '=', 'transaksi_gaji.karyawan_id')
                ->leftJoin('unit_kerja', 'unit_kerja.id', '=', 'karyawan.unit_kerja_id')
                ->orderBy('unit_kerja.nama_unit', $direction);
        } elseif (in_array($sort, ['gaji_pokok', 'gaji_bersih', 'tunjangan', 'potongan'])) {
            $withCols->orderBy($sort, $direction);
        } else {
            $withCols->join('karyawan', 'karyawan.id', '=', 'transaksi_gaji.karyawan_id')
                ->orderBy('karyawan.nama_lengkap', $direction);
        }

        return [
            'transaksiGaji' => $withCols->get(),
            'totalTransaksi' => $ringkasan['totalTransaksi'],
            'totalGajiPokok' => $ringkasan['totalGajiPokok'],
            'totalTunjangan' => $totalTunjangan,
            'totalPotongan' => $totalPotongan,
            'totalGajiBersih' => $ringkasan['totalGajiBersih'],
            'rekapUnitKerja' => $this->laporanRepository->rekapUnitKerjaPenggajian($query),
            'selectedUnitKerja' => $this->selectedUnitKerja($filters['unit_kerja_id'] ?? null),
        ];
    }

    public function penggajianExportRows(array $filters, int $bulan, int $tahun): Builder
    {
        $query = $this->laporanRepository->penggajianQuery($filters, $bulan, $tahun);
        $sort = $filters['sort'] ?? 'karyawan';
        $direction = strtolower($filters['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        $withCols = $this->laporanRepository->withTunjanganPotongan($query);

        if ($sort === 'unit_kerja') {
            return $withCols->join('karyawan', 'karyawan.id', '=', 'transaksi_gaji.karyawan_id')
                ->leftJoin('unit_kerja', 'unit_kerja.id', '=', 'karyawan.unit_kerja_id')
                ->orderBy('unit_kerja.nama_unit', $direction);
        } elseif (in_array($sort, ['gaji_pokok', 'gaji_bersih', 'tunjangan', 'potongan'])) {
            return $withCols->orderBy($sort, $direction);
        }

        return $withCols->join('karyawan', 'karyawan.id', '=', 'transaksi_gaji.karyawan_id')
            ->orderBy('karyawan.nama_lengkap', $direction);
    }

    public function penyusutan(array $filters, int $tahun, int $perPage = PerPage::DEFAULT): array
    {
        $query = $this->laporanRepository->penyusutanQuery($filters, $tahun);
        $semuaBarang = (clone $query)->get();
        $semuaRincian = $this->rincianPenyusutan($semuaBarang, $tahun);
        $ringkasan = $this->ringkasanPenyusutan($semuaBarang, $semuaRincian);

        $sort = $filters['sort'] ?? 'kode_barang';
        $direction = strtolower($filters['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        $sorted = $semuaBarang->sortBy(function (Barang $barang) use ($sort, $semuaRincian) {
            $r = $semuaRincian->get($barang->id);
            return match ($sort) {
                'nama_barang' => strtolower($barang->nama_barang),
                'kategori' => $barang->kategori,
                'harga_perolehan' => (float) $barang->harga_perolehan,
                'akumulasi_awal_tahun' => (float) ($r['akumulasi_awal_tahun'] ?? 0),
                'penyusutan_tahun_ini' => (float) ($r['penyusutan_tahun_ini'] ?? 0),
                'akumulasi_akhir_tahun' => (float) ($r['akumulasi_akhir_tahun'] ?? 0),
                'nilai_buku' => (float) ($r['nilai_buku_akhir_tahun'] ?? 0),
                default => $barang->kode_barang,
            };
        }, SORT_REGULAR, $direction === 'desc')->values();

        $page = (int) request('page', 1);
        $items = $sorted->slice(($page - 1) * $perPage, $perPage)->values();

        $barangs = new \Illuminate\Pagination\LengthAwarePaginator(
            $items,
            $sorted->count(),
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );

        return [
            'barangs' => $barangs,
            'rincian' => $semuaRincian,
            'unitKerjas' => isset($filters['koperasi_id'])
                ? $this->unitKerjaRepository->orderedList((int) $filters['koperasi_id'])
                : $this->unitKerjaRepository->orderedList(),
            ...$ringkasan,
        ];
    }

    public function penyusutanCetak(array $filters, int $tahun): array
    {
        $query = $this->laporanRepository->penyusutanQuery($filters, $tahun);
        $semuaBarang = (clone $query)->get();
        $semuaRincian = $this->rincianPenyusutan($semuaBarang, $tahun);
        $ringkasan = $this->ringkasanPenyusutan($semuaBarang, $semuaRincian);

        $sort = $filters['sort'] ?? 'kode_barang';
        $direction = strtolower($filters['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        $sorted = $semuaBarang->sortBy(function (Barang $barang) use ($sort, $semuaRincian) {
            $r = $semuaRincian->get($barang->id);
            return match ($sort) {
                'nama_barang' => strtolower($barang->nama_barang),
                'kategori' => $barang->kategori,
                'harga_perolehan' => (float) $barang->harga_perolehan,
                'akumulasi_awal_tahun' => (float) ($r['akumulasi_awal_tahun'] ?? 0),
                'penyusutan_tahun_ini' => (float) ($r['penyusutan_tahun_ini'] ?? 0),
                'akumulasi_akhir_tahun' => (float) ($r['akumulasi_akhir_tahun'] ?? 0),
                'nilai_buku' => (float) ($r['nilai_buku_akhir_tahun'] ?? 0),
                default => $barang->kode_barang,
            };
        }, SORT_REGULAR, $direction === 'desc')->values();

        return [
            'barangs' => $sorted,
            'rincian' => $semuaRincian,
            'tahun' => $tahun,
            'selectedUnitKerja' => $this->selectedUnitKerja($filters['unit_kerja_id'] ?? null),
            ...$ringkasan,
        ];
    }

    /**
     * @return Collection<int, array{barang: Barang, metode: string, masa_manfaat_tahun: int, bulan_mulai: Carbon, bulan_selesai: Carbon, akumulasi_awal_tahun: string, penyusutan_tahun_ini: string, akumulasi_akhir_tahun: string, nilai_buku_akhir_tahun: string}>
     */
    public function penyusutanExportRows(array $filters, int $tahun): Collection
    {
        $query = $this->laporanRepository->penyusutanQuery($filters, $tahun);
        $semuaBarang = (clone $query)->get();
        $semuaRincian = $this->rincianPenyusutan($semuaBarang, $tahun);

        $sort = $filters['sort'] ?? 'kode_barang';
        $direction = strtolower($filters['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        $sorted = $semuaBarang->sortBy(function (Barang $barang) use ($sort, $semuaRincian) {
            $r = $semuaRincian->get($barang->id);
            return match ($sort) {
                'nama_barang' => strtolower($barang->nama_barang),
                'kategori' => $barang->kategori,
                'harga_perolehan' => (float) $barang->harga_perolehan,
                'akumulasi_awal_tahun' => (float) ($r['akumulasi_awal_tahun'] ?? 0),
                'penyusutan_tahun_ini' => (float) ($r['penyusutan_tahun_ini'] ?? 0),
                'akumulasi_akhir_tahun' => (float) ($r['akumulasi_akhir_tahun'] ?? 0),
                'nilai_buku' => (float) ($r['nilai_buku_akhir_tahun'] ?? 0),
                default => $barang->kode_barang,
            };
        }, SORT_REGULAR, $direction === 'desc')->values();

        return $sorted->map(fn (Barang $barang) => ['barang' => $barang, ...$semuaRincian->get($barang->id)]);
    }

    /**
     * @return Collection<int, array> Rincian penyusutan tahun $tahun, keyed per barang id.
     */
    private function rincianPenyusutan(Collection $barangs, int $tahun): Collection
    {
        return $barangs->mapWithKeys(fn (Barang $barang) => [
            $barang->id => PenyusutanCalculator::hitungTahunan(
                $barang->kategori,
                (string) $barang->harga_perolehan,
                $barang->tanggal_perolehan,
                $tahun,
            ),
        ]);
    }

    /**
     * @return array{totalAset: int, totalHargaPerolehan: string, totalAkumulasiAwalTahun: string, totalPenyusutanTahunIni: string, totalAkumulasiPenyusutan: string, totalNilaiBuku: string}
     */
    private function ringkasanPenyusutan(Collection $barangs, Collection $rincian): array
    {
        return [
            'totalAset' => $barangs->count(),
            'totalHargaPerolehan' => (string) $barangs->reduce(fn ($total, Barang $barang) => bcadd($total, (string) $barang->harga_perolehan, 2), '0.00'),
            'totalAkumulasiAwalTahun' => $rincian->reduce(fn ($total, $r) => bcadd($total, $r['akumulasi_awal_tahun'], 2), '0.00'),
            'totalPenyusutanTahunIni' => $rincian->reduce(fn ($total, $r) => bcadd($total, $r['penyusutan_tahun_ini'], 2), '0.00'),
            'totalAkumulasiPenyusutan' => $rincian->reduce(fn ($total, $r) => bcadd($total, $r['akumulasi_akhir_tahun'], 2), '0.00'),
            'totalNilaiBuku' => $rincian->reduce(fn ($total, $r) => bcadd($total, $r['nilai_buku_akhir_tahun'], 2), '0.00'),
        ];
    }

    /** @return array{totalBarang: int, totalNilai: string} */
    private function ringkasanNilaiInventaris(Collection $barangs): array
    {
        return [
            'totalBarang' => $barangs->count(),
            'totalNilai' => $barangs->reduce(
                fn (string $total, Barang $barang) => bcadd($total, $barang->nilaiBukuTerakhir(), 2),
                '0.00',
            ),
        ];
    }

    /**
     * Rekapitulasi jumlah dan total nilai buku per kategori/golongan.
     * Mengikuti urutan alami golongan aset tetap fiskal.
     */
    private function rekapKategoriInventaris(Collection $barangs): Collection
    {
        $semuaKategori = collect(config('inventaris.kategori'));
        $grouped = $barangs->groupBy('kategori');

        return $semuaKategori->map(function (string $kategori) use ($grouped) {
            $items = $grouped->get($kategori, collect());

            return (object) [
                'kategori' => $kategori,
                'total_barang' => $items->count(),
                'total_nilai' => $items->reduce(
                    fn (string $total, Barang $barang) => bcadd($total, $barang->nilaiBukuTerakhir(), 2),
                    '0.00',
                ),
            ];
        })->filter(fn ($r) => $r->total_barang > 0)->values();
    }

    private function selectedUnitKerja(?string $unitKerjaId)
    {
        return $unitKerjaId ? $this->unitKerjaRepository->find((int) $unitKerjaId) : null;
    }

    private function selectedKaryawan(?string $karyawanId)
    {
        return $karyawanId ? $this->karyawanRepository->find((int) $karyawanId) : null;
    }
}
