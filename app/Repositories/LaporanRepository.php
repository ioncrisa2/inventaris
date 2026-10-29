<?php

namespace App\Repositories;

use App\Models\Absensi;
use App\Models\Barang;
use App\Models\Karyawan;
use App\Models\TransaksiGaji;
use App\Models\TransaksiGajiDetail;
use App\Support\KondisiBarangHelper;
use App\Support\PenyusutanCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LaporanRepository
{
    /**
     * @param  array{koperasi_id?: ?int, unit_kerja_id?: ?string, kategori?: ?string, kondisi?: ?string, tanggal_awal?: ?string, tanggal_akhir?: ?string, search?: ?string}  $filters
     */
    public function inventarisQuery(array $filters): Builder
    {
        $query = Barang::query()
            ->with([
                'koperasi:id,nama',
                'unitKerja:id,nama_unit',
                'kondisiTerakhir' => fn ($query) => $query->select([
                    'riwayat_kondisi_barang.id',
                    'riwayat_kondisi_barang.barang_id',
                    'riwayat_kondisi_barang.tanggal_pemeriksaan',
                    'riwayat_kondisi_barang.kondisi',
                ]),
            ])
            ->when($filters['koperasi_id'] ?? null, fn ($query, $koperasiId) => $query->where('koperasi_id', $koperasiId))
            ->when($filters['unit_kerja_id'] ?? null, function ($query, $unitKerjaId) {
                $query->where('unit_kerja_id', $unitKerjaId);
            })
            ->when($filters['kategori'] ?? null, function ($query, $kategori) {
                $query->where('kategori', $kategori);
            })
            ->when($filters['kondisi'] ?? null, function ($query, $kondisi) {
                $query->whereHas('kondisiTerakhir', fn ($q) => $q->where('kondisi', $kondisi));
            })
            ->when($filters['tanggal_awal'] ?? null, function ($query, $tanggalAwal) {
                $query->where('tanggal_perolehan', '>=', $tanggalAwal);
            })
            ->when($filters['tanggal_akhir'] ?? null, function ($query, $tanggalAkhir) {
                $query->where('tanggal_perolehan', '<', CarbonImmutable::parse($tanggalAkhir)->addDay()->toDateString());
            })
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('kode_barang', 'like', "%{$search}%")
                        ->orWhere('nama_barang', 'like', "%{$search}%")
                        ->orWhere('jenis_barang', 'like', "%{$search}%");
                });
            });

        return $this->filterMasihBernilai($query);
    }

    public function rekapKategoriInventaris(Builder $query): Collection
    {
        return (clone $query)
            ->select('kategori', DB::raw('COUNT(*) as total_barang'), DB::raw('SUM(harga_perolehan) as total_nilai'))
            ->groupBy('kategori')
            ->orderBy('kategori')
            ->get();
    }

    /** @return array{totalBarang: int, totalNilai: string} */
    public function ringkasanInventaris(Builder $query): array
    {
        $ringkasan = (clone $query)
            ->without(['unitKerja', 'kondisiTerakhir'])
            ->selectRaw('COUNT(*) AS total_barang')
            ->selectRaw('COALESCE(SUM(harga_perolehan), 0) AS total_nilai')
            ->first();

        return [
            'totalBarang' => (int) $ringkasan->total_barang,
            'totalNilai' => (string) $ringkasan->total_nilai,
        ];
    }

    public function barangPerluPerbaikan(Builder $query): int
    {
        return (clone $query)
            ->whereHas('kondisiTerakhir', function ($query) {
                $query->whereIn('kondisi', KondisiBarangHelper::KONDISI_PERLU_PERBAIKAN);
            })
            ->count();
    }

    /**
     * @param  array{koperasi_id?: ?int, unit_kerja_id?: ?string, kategori?: ?string, search?: ?string}  $filters
     */
    public function penyusutanQuery(array $filters, int $tahun): Builder
    {
        $akhirTahun = CarbonImmutable::create($tahun, 12, 31)->toDateString();

        $query = Barang::query()
            ->with([
                'koperasi:id,nama',
                'unitKerja:id,koperasi_id,nama_unit',
                'unitKerja.koperasi:id,nama',
                'kondisiTerakhir' => fn ($query) => $query->select([
                    'riwayat_kondisi_barang.id',
                    'riwayat_kondisi_barang.barang_id',
                    'riwayat_kondisi_barang.tanggal_pemeriksaan',
                    'riwayat_kondisi_barang.kondisi',
                ]),
            ])
            ->where('tanggal_perolehan', '<=', $akhirTahun)
            ->when($filters['koperasi_id'] ?? null, fn ($query, $koperasiId) => $query->where('koperasi_id', $koperasiId))
            ->when($filters['unit_kerja_id'] ?? null, function ($query, $unitKerjaId) {
                $query->where('unit_kerja_id', $unitKerjaId);
            })
            ->when($filters['kategori'] ?? null, function ($query, $kategori) {
                $query->where('kategori', $kategori);
            })
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('kode_barang', 'like', "%{$search}%")
                        ->orWhere('nama_barang', 'like', "%{$search}%");
                });
            });

        return $this->filterMasihBernilai($query, $tahun);
    }

    private function filterMasihBernilai(Builder $query, ?int $tahun = null): Builder
    {
        $all = (clone $query)->get();
        $targetTahun = $tahun ?? PenyusutanCalculator::tahunBerjalan();

        $validIds = $all->filter(function (Barang $b) use ($targetTahun) {
            if ($b->kondisiTerakhir?->kondisi === 'Dihapus') {
                return false;
            }

            try {
                $r = PenyusutanCalculator::hitungTahunan(
                    $b->kategori,
                    (string) $b->harga_perolehan,
                    $b->tanggal_perolehan,
                    $targetTahun,
                );

                return bccomp($r['nilai_buku_akhir_tahun'], '0.00', 2) > 0;
            } catch (\InvalidArgumentException) {
                return true;
            }
        })->pluck('id');

        return $query->whereIn('barang.id', $validIds);
    }

    /**
     * @param  array{koperasi_id?: ?int, unit_kerja_id?: ?string, karyawan_id?: ?string, status?: ?string, tipe_periode?: ?string, search?: ?string}  $filters
     */
    public function absensiQuery(array $filters, int $bulan, int $tahun): Builder
    {
        if (($filters['tipe_periode'] ?? 'kalender') === 'penggajian') {
            $mulai = CarbonImmutable::create($tahun, $bulan, 1)->subMonthNoOverflow()->setDay(25)->startOfDay();
            $selesaiEksklusif = CarbonImmutable::create($tahun, $bulan, 25)->startOfDay();
        } else {
            $mulai = CarbonImmutable::create($tahun, $bulan, 1)->startOfDay();
            $selesaiEksklusif = $mulai->addMonthNoOverflow();
        }

        return Absensi::query()
            ->with([
                'koperasi:id,nama',
                'karyawan:id,koperasi_id,unit_kerja_id,nik,nama_lengkap',
                'karyawan.unitKerja:id,koperasi_id,nama_unit',
                'karyawan.unitKerja.koperasi:id,nama',
            ])
            ->where('tanggal', '>=', $mulai->toDateString())
            ->where('tanggal', '<', $selesaiEksklusif->toDateString())
            ->when($filters['koperasi_id'] ?? null, fn ($query, $koperasiId) => $query->where('koperasi_id', $koperasiId))
            ->when($filters['karyawan_id'] ?? null, function ($query, $karyawanId) {
                $query->where('karyawan_id', $karyawanId);
            })
            ->when($filters['unit_kerja_id'] ?? null, function ($query, $unitKerjaId) {
                $query->whereHas('karyawan', fn ($q) => $q->where('unit_kerja_id', $unitKerjaId));
            })
            ->when($filters['status'] ?? null, function ($query, $status) {
                if ($status === 'Dinas' || in_array($status, Absensi::DINAS_STATUSES, true)) {
                    $query->whereIn('status', Absensi::DINAS_STATUSES);
                } else {
                    $query->where('status', $status);
                }
            })
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->whereHas('karyawan', function ($q) use ($search) {
                    $q->where('nama_lengkap', 'like', "%{$search}%")
                        ->orWhere('nik', 'like', "%{$search}%");
                });
            });
    }

    /**
     * @return array{totalHadir: int, totalIzin: int, totalSakit: int, totalCuti: int, totalDinasLuarKota: int, totalDinas: int, totalAlpha: int}
     */
    public function totalPerStatusAbsensi(Builder $query): array
    {
        $jumlah = (clone $query)
            ->without('karyawan')
            ->select('status')
            ->selectRaw('COUNT(*) AS total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $totalDinas = collect(Absensi::DINAS_STATUSES)->sum(fn (string $status) => (int) $jumlah->get($status, 0));

        return [
            'totalHadir' => (int) $jumlah->get('Hadir', 0),
            'totalIzin' => (int) $jumlah->get('Izin', 0),
            'totalSakit' => (int) $jumlah->get('Sakit', 0),
            'totalCuti' => (int) $jumlah->get('Cuti', 0),
            'totalDinasLuarKota' => $totalDinas,
            'totalDinas' => $totalDinas,
            'totalAlpha' => (int) $jumlah->get('Alpha', 0),
        ];
    }

    /**
     * Rekap kehadiran per karyawan berdasarkan query absensi yang difilter.
     */
    public function absensiPerKaryawanQuery(Builder $baseQuery): \Illuminate\Database\Query\Builder
    {
        $attendanceIds = (clone $baseQuery)->without('karyawan')->select('absensi.id');

        return DB::table('absensi')
            ->join('karyawan', 'karyawan.id', '=', 'absensi.karyawan_id')
            ->leftJoin('unit_kerja', 'unit_kerja.id', '=', 'karyawan.unit_kerja_id')
            ->leftJoin('koperasi', 'koperasi.id', '=', 'karyawan.koperasi_id')
            ->whereIn('absensi.id', $attendanceIds)
            ->select([
                'karyawan.id AS karyawan_id',
                'karyawan.nik',
                'karyawan.nama_lengkap',
                'unit_kerja.id AS unit_kerja_id',
                'unit_kerja.nama_unit',
                'koperasi.nama AS nama_koperasi',
            ])
            ->selectRaw("SUM(CASE WHEN absensi.status = 'Hadir' THEN 1 ELSE 0 END) AS hadir")
            ->selectRaw("SUM(CASE WHEN absensi.status = 'Izin' THEN 1 ELSE 0 END) AS izin")
            ->selectRaw("SUM(CASE WHEN absensi.status = 'Sakit' THEN 1 ELSE 0 END) AS sakit")
            ->selectRaw("SUM(CASE WHEN absensi.status = 'Cuti' THEN 1 ELSE 0 END) AS cuti")
            ->selectRaw("SUM(CASE WHEN absensi.status IN ('Dinas', 'Dinas Luar', 'Dinas Luar Kota') THEN 1 ELSE 0 END) AS dinas")
            ->selectRaw("SUM(CASE WHEN absensi.status = 'Alpha' THEN 1 ELSE 0 END) AS alpha")
            ->selectRaw("COUNT(*) AS total_hari")
            ->groupBy('karyawan.id', 'karyawan.nik', 'karyawan.nama_lengkap', 'unit_kerja.id', 'unit_kerja.nama_unit', 'koperasi.nama');
    }

    /**
     * @param  array{koperasi_id?: ?int, unit_kerja_id?: ?string, status_karyawan?: ?string, keaktifan?: ?string, search?: ?string}  $filters
     */
    public function kepegawaianQuery(array $filters): Builder
    {
        return Karyawan::query()
            ->with(['koperasi:id,nama', 'unitKerja:id,koperasi_id,nama_unit', 'unitKerja.koperasi:id,nama'])
            ->when($filters['koperasi_id'] ?? null, fn ($query, $koperasiId) => $query->where('koperasi_id', $koperasiId))
            ->when($filters['unit_kerja_id'] ?? null, function ($query, $unitKerjaId) {
                $query->where('unit_kerja_id', $unitKerjaId);
            })
            ->when($filters['status_karyawan'] ?? $filters['status'] ?? null, function ($query, $status) {
                $query->where('status_karyawan', $status);
            })
            ->when($filters['keaktifan'] ?? null, function ($query, $keaktifan) {
                if ($keaktifan === 'aktif') {
                    $query->whereNull('tanggal_mengundurkan_diri');
                } elseif ($keaktifan === 'nonaktif') {
                    $query->whereNotNull('tanggal_mengundurkan_diri');
                }
            })
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('nama_lengkap', 'like', "%{$search}%")
                        ->orWhere('nik', 'like', "%{$search}%");
                });
            });
    }

    public function rekapStatusKepegawaian(Builder $query): Collection
    {
        return (clone $query)
            ->without('unitKerja')
            ->select('status_karyawan', DB::raw('COUNT(*) as total_karyawan'))
            ->groupBy('status_karyawan')
            ->orderBy('status_karyawan')
            ->get();
    }

    /** @return array{totalKaryawan: int, totalAktif: int, totalMengundurkanDiri: int, totalGajiAktif: string, totalGajiSemua: string} */
    public function ringkasanKepegawaian(Builder $query): array
    {
        $ringkasan = (clone $query)
            ->without('unitKerja')
            ->selectRaw('COUNT(*) AS total_karyawan')
            ->selectRaw('SUM(CASE WHEN tanggal_mengundurkan_diri IS NULL THEN 1 ELSE 0 END) AS total_aktif')
            ->selectRaw('SUM(CASE WHEN tanggal_mengundurkan_diri IS NOT NULL THEN 1 ELSE 0 END) AS total_mengundurkan_diri')
            ->selectRaw('COALESCE(SUM(CASE WHEN tanggal_mengundurkan_diri IS NULL THEN gaji_pokok ELSE 0 END), 0) AS total_gaji_aktif')
            ->selectRaw('COALESCE(SUM(gaji_pokok), 0) AS total_gaji_semua')
            ->first();

        return [
            'totalKaryawan' => (int) $ringkasan->total_karyawan,
            'totalAktif' => (int) $ringkasan->total_aktif,
            'totalMengundurkanDiri' => (int) $ringkasan->total_mengundurkan_diri,
            'totalGajiAktif' => (string) $ringkasan->total_gaji_aktif,
            'totalGajiSemua' => (string) $ringkasan->total_gaji_semua,
        ];
    }

    public function rekapUnitKerjaKepegawaian(Builder $query): Collection
    {
        return (clone $query)
            ->with(['unitKerja:id,koperasi_id,nama_unit', 'unitKerja.koperasi:id,nama'])
            ->select('unit_kerja_id', DB::raw('COUNT(*) as total_karyawan'))
            ->groupBy('unit_kerja_id')
            ->orderByDesc('total_karyawan')
            ->get();
    }

    /**
     * @param  array{koperasi_id?: ?int, unit_kerja_id?: ?string, search?: ?string}  $filters
     */
    public function penggajianQuery(array $filters, int $bulan, int $tahun): Builder
    {
        return TransaksiGaji::query()
            ->with([
                'koperasi:id,nama',
                'karyawan' => fn ($query) => $query->select(['id', 'koperasi_id', 'unit_kerja_id', 'nik', 'nama_lengkap', 'jabatan']),
                'karyawan.unitKerja:id,koperasi_id,nama_unit',
                'karyawan.unitKerja.koperasi:id,nama',
            ])
            ->where('bulan', $bulan)
            ->where('tahun', $tahun)
            ->when($filters['koperasi_id'] ?? null, fn ($query, $koperasiId) => $query->where('koperasi_id', $koperasiId))
            ->when($filters['unit_kerja_id'] ?? null, function ($query, $unitKerjaId) {
                $query->whereHas('karyawan', function ($query) use ($unitKerjaId) {
                    $query->where('unit_kerja_id', $unitKerjaId);
                });
            })
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->whereHas('karyawan', function ($q) use ($search) {
                    $q->where('nama_lengkap', 'like', "%{$search}%")
                        ->orWhere('nik', 'like', "%{$search}%");
                });
            });
    }

    public function withTunjanganPotongan(Builder $query): Builder
    {
        return (clone $query)->select('transaksi_gaji.*')
            ->selectSub(
                TransaksiGajiDetail::selectRaw("COALESCE(SUM(nominal_hasil), 0)")
                    ->whereColumn('transaksi_gaji_id', 'transaksi_gaji.id')
                    ->where('jenis_snapshot', 'Tunjangan'),
                'tunjangan'
            )
            ->selectSub(
                TransaksiGajiDetail::selectRaw("COALESCE(SUM(nominal_hasil), 0)")
                    ->whereColumn('transaksi_gaji_id', 'transaksi_gaji.id')
                    ->where('jenis_snapshot', 'Potongan'),
                'potongan'
            );
    }

    /**
     * @return array{0: string, 1: string} [$totalTunjangan, $totalPotongan]
     */
    public function totalTunjanganPotongan(Builder $query): array
    {
        $transaksiGajiIds = (clone $query)
            ->without('karyawan')
            ->select('transaksi_gaji.id');

        $ringkasan = TransaksiGajiDetail::query()
            ->whereIn('transaksi_gaji_id', $transaksiGajiIds)
            ->selectRaw("COALESCE(SUM(CASE WHEN jenis_snapshot = 'Tunjangan' THEN nominal_hasil ELSE 0 END), 0) AS total_tunjangan")
            ->selectRaw("COALESCE(SUM(CASE WHEN jenis_snapshot = 'Potongan' THEN nominal_hasil ELSE 0 END), 0) AS total_potongan")
            ->first();

        return [(string) $ringkasan->total_tunjangan, (string) $ringkasan->total_potongan];
    }

    /** @return array{totalTransaksi: int, totalGajiPokok: string, totalGajiBersih: string} */
    public function ringkasanPenggajian(Builder $query): array
    {
        $ringkasan = (clone $query)
            ->without('karyawan')
            ->selectRaw('COUNT(*) AS total_transaksi')
            ->selectRaw('COALESCE(SUM(gaji_pokok), 0) AS total_gaji_pokok')
            ->selectRaw('COALESCE(SUM(gaji_bersih), 0) AS total_gaji_bersih')
            ->first();

        return [
            'totalTransaksi' => (int) $ringkasan->total_transaksi,
            'totalGajiPokok' => (string) $ringkasan->total_gaji_pokok,
            'totalGajiBersih' => (string) $ringkasan->total_gaji_bersih,
        ];
    }

    /** Rekap jumlah transaksi dan total gaji bersih per unit melalui SQL join. */
    public function rekapUnitKerjaPenggajian(Builder $query): Collection
    {
        $transaksiGajiIds = (clone $query)
            ->without('karyawan')
            ->select('transaksi_gaji.id');

        return DB::table('transaksi_gaji')
            ->join('karyawan', 'karyawan.id', '=', 'transaksi_gaji.karyawan_id')
            ->join('unit_kerja', 'unit_kerja.id', '=', 'karyawan.unit_kerja_id')
            ->join('koperasi', 'koperasi.id', '=', 'unit_kerja.koperasi_id')
            ->whereIn('transaksi_gaji.id', $transaksiGajiIds)
            ->select('unit_kerja.id AS unit_kerja_id', 'unit_kerja.nama_unit', 'koperasi.nama AS nama_koperasi')
            ->selectRaw('COUNT(*) AS total_transaksi')
            ->selectRaw('COALESCE(SUM(transaksi_gaji.gaji_bersih), 0) AS total_gaji_bersih')
            ->groupBy('unit_kerja.id', 'unit_kerja.nama_unit', 'koperasi.nama')
            ->orderByDesc('total_gaji_bersih')
            ->get();
    }
}
