<?php

namespace App\Repositories;

use App\Models\Absensi;
use App\Models\Barang;
use App\Models\Karyawan;
use App\Models\TransaksiGaji;
use App\Models\TransaksiGajiDetail;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LaporanRepository
{
    /**
     * @param  array{unit_kerja_id?: ?string, kategori?: ?string, tanggal_awal?: ?string, tanggal_akhir?: ?string}  $filters
     */
    public function inventarisQuery(array $filters): Builder
    {
        return Barang::query()
            ->with([
                'unitKerja:id,nama_unit',
                'kondisiTerakhir' => fn ($query) => $query->select([
                    'riwayat_kondisi_barang.id',
                    'riwayat_kondisi_barang.barang_id',
                    'riwayat_kondisi_barang.tanggal_pemeriksaan',
                    'riwayat_kondisi_barang.kondisi',
                ]),
            ])
            ->when($filters['unit_kerja_id'] ?? null, function ($query, $unitKerjaId) {
                $query->where('unit_kerja_id', $unitKerjaId);
            })
            ->when($filters['kategori'] ?? null, function ($query, $kategori) {
                $query->where('kategori', $kategori);
            })
            ->when($filters['tanggal_awal'] ?? null, function ($query, $tanggalAwal) {
                $query->where('tanggal_perolehan', '>=', $tanggalAwal);
            })
            ->when($filters['tanggal_akhir'] ?? null, function ($query, $tanggalAkhir) {
                $query->where('tanggal_perolehan', '<', CarbonImmutable::parse($tanggalAkhir)->addDay()->toDateString());
            });
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
                $query->whereIn('kondisi', ['Rusak Ringan', 'Rusak Berat']);
            })
            ->count();
    }

    /**
     * @param  array{karyawan_id?: ?string}  $filters
     */
    public function absensiQuery(array $filters, int $bulan, int $tahun): Builder
    {
        $mulai = CarbonImmutable::create($tahun, $bulan, 1)->startOfDay();
        $selesaiEksklusif = $mulai->addMonthNoOverflow();

        return Absensi::query()
            ->with('karyawan.unitKerja:id,nama_unit')
            ->where('tanggal', '>=', $mulai->toDateString())
            ->where('tanggal', '<', $selesaiEksklusif->toDateString())
            ->when($filters['karyawan_id'] ?? null, function ($query, $karyawanId) {
                $query->where('karyawan_id', $karyawanId);
            });
    }

    /**
     * @return array{totalHadir: int, totalIzin: int, totalSakit: int, totalCuti: int, totalDinasLuarKota: int, totalAlpha: int}
     */
    public function totalPerStatusAbsensi(Builder $query): array
    {
        $jumlah = (clone $query)
            ->without('karyawan')
            ->select('status')
            ->selectRaw('COUNT(*) AS total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'totalHadir' => (int) $jumlah->get('Hadir', 0),
            'totalIzin' => (int) $jumlah->get('Izin', 0),
            'totalSakit' => (int) $jumlah->get('Sakit', 0),
            'totalCuti' => (int) $jumlah->get('Cuti', 0),
            'totalDinasLuarKota' => (int) $jumlah->get('Dinas Luar Kota', 0),
            'totalAlpha' => (int) $jumlah->get('Alpha', 0),
        ];
    }

    /**
     * @param  array{unit_kerja_id?: ?string, status_karyawan?: ?string}  $filters
     */
    public function kepegawaianQuery(array $filters): Builder
    {
        return Karyawan::query()
            ->with('unitKerja:id,nama_unit')
            ->when($filters['unit_kerja_id'] ?? null, function ($query, $unitKerjaId) {
                $query->where('unit_kerja_id', $unitKerjaId);
            })
            ->when($filters['status_karyawan'] ?? null, function ($query, $status) {
                $query->where('status_karyawan', $status);
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

    /** @return array{totalKaryawan: int, totalAktif: int, totalMengundurkanDiri: int, totalGajiAktif: string} */
    public function ringkasanKepegawaian(Builder $query): array
    {
        $ringkasan = (clone $query)
            ->without('unitKerja')
            ->selectRaw('COUNT(*) AS total_karyawan')
            ->selectRaw('SUM(CASE WHEN tanggal_mengundurkan_diri IS NULL THEN 1 ELSE 0 END) AS total_aktif')
            ->selectRaw('SUM(CASE WHEN tanggal_mengundurkan_diri IS NOT NULL THEN 1 ELSE 0 END) AS total_mengundurkan_diri')
            ->selectRaw('COALESCE(SUM(CASE WHEN tanggal_mengundurkan_diri IS NULL THEN gaji_pokok ELSE 0 END), 0) AS total_gaji_aktif')
            ->first();

        return [
            'totalKaryawan' => (int) $ringkasan->total_karyawan,
            'totalAktif' => (int) $ringkasan->total_aktif,
            'totalMengundurkanDiri' => (int) $ringkasan->total_mengundurkan_diri,
            'totalGajiAktif' => (string) $ringkasan->total_gaji_aktif,
        ];
    }

    public function rekapUnitKerjaKepegawaian(Builder $query): Collection
    {
        return (clone $query)
            ->select('unit_kerja_id', DB::raw('COUNT(*) as total_karyawan'))
            ->groupBy('unit_kerja_id')
            ->get();
    }

    /**
     * @param  array{unit_kerja_id?: ?string}  $filters
     */
    public function penggajianQuery(array $filters, int $bulan, int $tahun): Builder
    {
        return TransaksiGaji::query()
            ->with('karyawan.unitKerja:id,nama_unit')
            ->where('bulan', $bulan)
            ->where('tahun', $tahun)
            ->when($filters['unit_kerja_id'] ?? null, function ($query, $unitKerjaId) {
                $query->whereHas('karyawan', function ($query) use ($unitKerjaId) {
                    $query->where('unit_kerja_id', $unitKerjaId);
                });
            });
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
            ->whereIn('transaksi_gaji.id', $transaksiGajiIds)
            ->select('unit_kerja.id AS unit_kerja_id', 'unit_kerja.nama_unit')
            ->selectRaw('COUNT(*) AS total_transaksi')
            ->selectRaw('COALESCE(SUM(transaksi_gaji.gaji_bersih), 0) AS total_gaji_bersih')
            ->groupBy('unit_kerja.id', 'unit_kerja.nama_unit')
            ->orderBy('unit_kerja.nama_unit')
            ->get();
    }
}
