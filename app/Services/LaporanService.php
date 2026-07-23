<?php

namespace App\Services;

use App\Repositories\KaryawanRepository;
use App\Repositories\LaporanRepository;
use App\Repositories\UnitKerjaRepository;
use App\Support\PerPage;
use Illuminate\Database\Eloquent\Builder;

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

    private function selectedUnitKerja(?string $unitKerjaId)
    {
        return $unitKerjaId ? $this->unitKerjaRepository->find((int) $unitKerjaId) : null;
    }

    private function selectedKaryawan(?string $karyawanId)
    {
        return $karyawanId ? $this->karyawanRepository->find((int) $karyawanId) : null;
    }
}
