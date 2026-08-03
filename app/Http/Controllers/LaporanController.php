<?php

namespace App\Http\Controllers;

use App\Exports\AbsensiExport;
use App\Exports\InventarisExport;
use App\Exports\KepegawaianExport;
use App\Exports\PenggajianExport;
use App\Exports\PenyusutanExport;
use App\Http\Requests\Laporan\AbsensiLaporanRequest;
use App\Http\Requests\Laporan\InventarisLaporanRequest;
use App\Http\Requests\Laporan\KepegawaianLaporanRequest;
use App\Http\Requests\Laporan\PenggajianLaporanRequest;
use App\Http\Requests\Laporan\PenyusutanLaporanRequest;
use App\Services\LaporanService;
use App\Support\PerPage;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;

class LaporanController extends Controller
{
    public function __construct(
        private LaporanService $laporanService,
    ) {}

    public function inventaris(InventarisLaporanRequest $request)
    {
        return view('laporan.inventaris', [
            ...$this->laporanService->inventaris($request->validated(), PerPage::resolve($request)),
            ...$this->tenantFilterData($request),
        ]);
    }

    public function cetakInventaris(InventarisLaporanRequest $request)
    {
        return view('laporan.cetak.inventaris', $this->laporanService->inventarisCetak($request->validated()));
    }

    public function exportInventaris(InventarisLaporanRequest $request)
    {
        $barangs = $this->laporanService->inventarisExportRows($request->validated());

        return Excel::download(new InventarisExport($barangs), 'laporan-inventaris.xlsx');
    }

    public function absensi(AbsensiLaporanRequest $request)
    {
        $bulan = $request->bulan();
        $tahun = $request->tahun();

        return view('laporan.absensi', [
            ...$this->laporanService->absensi($request->validated(), $bulan, $tahun, PerPage::resolve($request)),
            'bulan' => $bulan,
            'tahun' => $tahun,
            ...$this->tenantFilterData($request),
        ]);
    }

    public function cetakAbsensi(AbsensiLaporanRequest $request)
    {
        $bulan = $request->bulan();
        $tahun = $request->tahun();

        return view('laporan.cetak.absensi', [
            ...$this->laporanService->absensiCetak($request->validated(), $bulan, $tahun),
            'bulan' => $bulan,
            'tahun' => $tahun,
        ]);
    }

    public function exportAbsensi(AbsensiLaporanRequest $request)
    {
        $absensis = $this->laporanService->absensiExportRows($request->validated(), $request->bulan(), $request->tahun());

        return Excel::download(new AbsensiExport($absensis), 'laporan-absensi.xlsx');
    }

    public function kepegawaian(KepegawaianLaporanRequest $request)
    {
        return view('laporan.kepegawaian', [
            ...$this->laporanService->kepegawaian($request->validated(), PerPage::resolve($request)),
            ...$this->tenantFilterData($request),
        ]);
    }

    public function cetakKepegawaian(KepegawaianLaporanRequest $request)
    {
        return view('laporan.cetak.kepegawaian', $this->laporanService->kepegawaianCetak($request->validated()));
    }

    public function exportKepegawaian(KepegawaianLaporanRequest $request)
    {
        $karyawans = $this->laporanService->kepegawaianExportRows($request->validated());

        return Excel::download(new KepegawaianExport($karyawans), 'laporan-kepegawaian.xlsx');
    }

    public function penggajian(PenggajianLaporanRequest $request)
    {
        $bulan = $request->bulan();
        $tahun = $request->tahun();

        return view('laporan.penggajian', [
            ...$this->laporanService->penggajian($request->validated(), $bulan, $tahun, PerPage::resolve($request)),
            'bulan' => $bulan,
            'tahun' => $tahun,
            ...$this->tenantFilterData($request),
        ]);
    }

    public function cetakPenggajian(PenggajianLaporanRequest $request)
    {
        $bulan = $request->bulan();
        $tahun = $request->tahun();

        return view('laporan.cetak.penggajian', [
            ...$this->laporanService->penggajianCetak($request->validated(), $bulan, $tahun),
            'bulan' => $bulan,
            'tahun' => $tahun,
        ]);
    }

    public function exportPenggajian(PenggajianLaporanRequest $request)
    {
        $transaksiGaji = $this->laporanService->penggajianExportRows($request->validated(), $request->bulan(), $request->tahun());

        return Excel::download(new PenggajianExport($transaksiGaji), 'laporan-penggajian.xlsx');
    }

    public function penyusutan(PenyusutanLaporanRequest $request)
    {
        $tahun = $request->tahun();

        return view('laporan.penyusutan', [
            ...$this->laporanService->penyusutan($request->validated(), $tahun, PerPage::resolve($request)),
            'tahun' => $tahun,
            ...$this->tenantFilterData($request),
        ]);
    }

    public function cetakPenyusutan(PenyusutanLaporanRequest $request)
    {
        return view('laporan.cetak.penyusutan', $this->laporanService->penyusutanCetak($request->validated(), $request->tahun()));
    }

    public function exportPenyusutan(PenyusutanLaporanRequest $request)
    {
        $rows = $this->laporanService->penyusutanExportRows($request->validated(), $request->tahun());

        return Excel::download(new PenyusutanExport($rows), 'laporan-penyusutan.xlsx');
    }

    /** @return array{koperasis: Collection, selectedKoperasiId: ?int} */
    private function tenantFilterData(Request $request): array
    {
        $koperasiId = $request->validated('koperasi_id');

        return TenantContext::filterViewData(
            $request,
            $koperasiId === null ? null : (int) $koperasiId,
        );
    }
}
