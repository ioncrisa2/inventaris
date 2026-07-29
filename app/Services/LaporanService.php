<?php

namespace App\Services;

use App\Models\Barang;
use App\Repositories\KaryawanRepository;
use App\Repositories\LaporanRepository;
use App\Repositories\UnitKerjaRepository;
use App\Support\PenyusutanCalculator;
use App\Support\PerPage;
use Illuminate\Database\Eloquent\Builder;
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
        $barangs = (clone $query)->latest()->paginate($perPage)->withQueryString();
        $ringkasan = $this->laporanRepository->ringkasanInventaris($query);

        return [
            'barangs' => $barangs,
            'totalBarang' => $barangs->total(),
            'totalNilai' => $ringkasan['totalNilai'],
            'barangPerluPerbaikan' => $this->laporanRepository->barangPerluPerbaikan($query),
            'rekapKategori' => $this->laporanRepository->rekapKategoriInventaris($query),
            'unitKerjas' => $this->unitKerjaRepository->orderedList(),
        ];
    }

    public function inventarisCetak(array $filters): array
    {
        $query = $this->laporanRepository->inventarisQuery($filters);
        $ringkasan = $this->laporanRepository->ringkasanInventaris($query);

        return [
            'barangs' => (clone $query)->orderBy('tanggal_perolehan')->orderBy('kode_barang')->get(),
            ...$ringkasan,
            'barangPerluPerbaikan' => $this->laporanRepository->barangPerluPerbaikan($query),
            'rekapKategori' => $this->laporanRepository->rekapKategoriInventaris($query),
            'selectedUnitKerja' => $this->selectedUnitKerja($filters['unit_kerja_id'] ?? null),
        ];
    }

    public function inventarisExportRows(array $filters): Builder
    {
        return $this->laporanRepository->inventarisQuery($filters)
            ->orderBy('tanggal_perolehan')
            ->orderBy('kode_barang');
    }

    public function absensi(array $filters, int $bulan, int $tahun, int $perPage = PerPage::DEFAULT): array
    {
        $query = $this->laporanRepository->absensiQuery($filters, $bulan, $tahun);

        return [
            'absensis' => (clone $query)->orderBy('tanggal')->paginate($perPage)->withQueryString(),
            'karyawans' => $this->karyawanRepository->orderedList(),
            'selectedKaryawanId' => $filters['karyawan_id'] ?? null,
            'selectedKaryawan' => $this->selectedKaryawan($filters['karyawan_id'] ?? null),
            ...$this->laporanRepository->totalPerStatusAbsensi($query),
        ];
    }

    public function absensiCetak(array $filters, int $bulan, int $tahun): array
    {
        $query = $this->laporanRepository->absensiQuery($filters, $bulan, $tahun);

        $absensis = (clone $query)->orderBy('tanggal')->orderBy('karyawan_id')->get();

        return [
            'absensis' => $absensis,
            'selectedKaryawan' => $this->selectedKaryawan($filters['karyawan_id'] ?? null),
            ...$this->laporanRepository->totalPerStatusAbsensi($query),
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
        $karyawans = (clone $query)->latest()->paginate($perPage)->withQueryString();
        $ringkasan = $this->laporanRepository->ringkasanKepegawaian($query);

        return [
            'karyawans' => $karyawans,
            'totalKaryawan' => $karyawans->total(),
            'totalAktif' => $ringkasan['totalAktif'],
            'totalGajiAktif' => $ringkasan['totalGajiAktif'],
            'rekapStatus' => $this->laporanRepository->rekapStatusKepegawaian($query),
            'rekapUnitKerja' => $this->laporanRepository->rekapUnitKerjaKepegawaian($query),
            'unitKerjas' => $this->unitKerjaRepository->orderedList(),
        ];
    }

    public function kepegawaianCetak(array $filters): array
    {
        $query = $this->laporanRepository->kepegawaianQuery($filters);
        $ringkasan = $this->laporanRepository->ringkasanKepegawaian($query);

        return [
            'karyawans' => (clone $query)->orderBy('nama_lengkap')->get(),
            ...$ringkasan,
            'rekapStatus' => $this->laporanRepository->rekapStatusKepegawaian($query),
            'rekapUnitKerja' => $this->laporanRepository->rekapUnitKerjaKepegawaian($query),
            'selectedUnitKerja' => $this->selectedUnitKerja($filters['unit_kerja_id'] ?? null),
        ];
    }

    public function kepegawaianExportRows(array $filters): Builder
    {
        return $this->laporanRepository->kepegawaianQuery($filters)
            ->orderBy('nama_lengkap');
    }

    public function penggajian(array $filters, int $bulan, int $tahun, int $perPage = PerPage::DEFAULT): array
    {
        $query = $this->laporanRepository->penggajianQuery($filters, $bulan, $tahun);
        [$totalTunjangan, $totalPotongan] = $this->laporanRepository->totalTunjanganPotongan($query);
        $transaksiGaji = (clone $query)->latest()->paginate($perPage)->withQueryString();
        $ringkasan = $this->laporanRepository->ringkasanPenggajian($query);

        return [
            'transaksiGaji' => $transaksiGaji,
            'totalTransaksi' => $transaksiGaji->total(),
            'totalGajiPokok' => $ringkasan['totalGajiPokok'],
            'totalTunjangan' => $totalTunjangan,
            'totalPotongan' => $totalPotongan,
            'totalGajiBersih' => $ringkasan['totalGajiBersih'],
            'rekapUnitKerja' => $this->laporanRepository->rekapUnitKerjaPenggajian($query),
            'unitKerjas' => $this->unitKerjaRepository->orderedList(),
        ];
    }

    public function penggajianCetak(array $filters, int $bulan, int $tahun): array
    {
        $query = $this->laporanRepository->penggajianQuery($filters, $bulan, $tahun);
        [$totalTunjangan, $totalPotongan] = $this->laporanRepository->totalTunjanganPotongan($query);
        $ringkasan = $this->laporanRepository->ringkasanPenggajian($query);

        return [
            'transaksiGaji' => (clone $query)->orderBy('karyawan_id')->get(),
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
        return $this->laporanRepository->penggajianQuery($filters, $bulan, $tahun)
            ->orderBy('karyawan_id');
    }

    public function penyusutan(array $filters, int $tahun, int $perPage = PerPage::DEFAULT): array
    {
        $query = $this->laporanRepository->penyusutanQuery($filters, $tahun);
        $barangs = (clone $query)->latest()->paginate($perPage)->withQueryString();
        $rincian = $this->rincianPenyusutan($barangs->getCollection(), $tahun);

        return [
            'barangs' => $barangs,
            'rincian' => $rincian,
            'unitKerjas' => $this->unitKerjaRepository->orderedList(),
            ...$this->ringkasanPenyusutan($barangs->getCollection(), $rincian),
        ];
    }

    public function penyusutanCetak(array $filters, int $tahun): array
    {
        $query = $this->laporanRepository->penyusutanQuery($filters, $tahun);
        $barangs = (clone $query)->orderBy('tanggal_perolehan')->orderBy('kode_barang')->get();
        $rincian = $this->rincianPenyusutan($barangs, $tahun);

        return [
            'barangs' => $barangs,
            'rincian' => $rincian,
            'tahun' => $tahun,
            'selectedUnitKerja' => $this->selectedUnitKerja($filters['unit_kerja_id'] ?? null),
            ...$this->ringkasanPenyusutan($barangs, $rincian),
        ];
    }

    /**
     * @return Collection<int, array{barang: Barang, metode: string, masa_manfaat_tahun: int, bulan_mulai: \Illuminate\Support\Carbon, bulan_selesai: \Illuminate\Support\Carbon, akumulasi_awal_tahun: string, penyusutan_tahun_ini: string, akumulasi_akhir_tahun: string, nilai_buku_akhir_tahun: string}>
     */
    public function penyusutanExportRows(array $filters, int $tahun): Collection
    {
        $barangs = $this->laporanRepository->penyusutanQuery($filters, $tahun)
            ->orderBy('tanggal_perolehan')
            ->orderBy('kode_barang')
            ->get();

        $rincian = $this->rincianPenyusutan($barangs, $tahun);

        return $barangs->map(fn (Barang $barang) => ['barang' => $barang, ...$rincian->get($barang->id)]);
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

    private function selectedUnitKerja(?string $unitKerjaId)
    {
        return $unitKerjaId ? $this->unitKerjaRepository->find((int) $unitKerjaId) : null;
    }

    private function selectedKaryawan(?string $karyawanId)
    {
        return $karyawanId ? $this->karyawanRepository->find((int) $karyawanId) : null;
    }
}
