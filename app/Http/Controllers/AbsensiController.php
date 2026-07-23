<?php

namespace App\Http\Controllers;

use App\Http\Requests\Absensi\AbsensiCalendarRequest;
use App\Http\Requests\Absensi\StoreAbsensiRequest;
use App\Models\Karyawan;
use App\Services\AbsensiService;
use App\Support\PerPage;
use Illuminate\Http\Request;

class AbsensiController extends Controller
{
    public function __construct(private AbsensiService $absensiService) {}

    /**
     * Halaman pemilihan karyawan sebelum masuk ke kalender absensinya.
     */
    public function index(Request $request)
    {
        $this->authorize('absensi.view');

        $karyawans = $this->absensiService->daftarKaryawan(
            $request->string('search')->trim()->value() ?: null,
            PerPage::resolve($request),
        );

        return view('absensi.index', compact('karyawans'));
    }

    /**
     * Kalender absensi satu bulan penuh untuk satu karyawan.
     */
    public function show(AbsensiCalendarRequest $request, Karyawan $karyawan)
    {
        $karyawan->load('unitKerja:id,nama_unit');
        $bulan = (int) $request->input('bulan', now()->month);
        $tahun = (int) $request->input('tahun', now()->year);

        $kalender = $this->absensiService->kalender($karyawan, $bulan, $tahun);

        return view('absensi.show', [
            'karyawan' => $karyawan,
            'bulan' => $bulan,
            'tahun' => $tahun,
            ...$kalender,
        ]);
    }

    /**
     * Simpan/perbarui absensi satu tanggal untuk satu karyawan.
     */
    public function store(StoreAbsensiRequest $request, Karyawan $karyawan)
    {
        $this->absensiService->simpan($karyawan, $request->validated());

        return back()->with('success', "Absensi {$karyawan->nama_lengkap} berhasil disimpan.");
    }
}
