<?php

namespace App\Http\Controllers;

use App\Services\AbsensiService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MyAttendanceController extends Controller
{
    public function __construct(private AbsensiService $absensiService) {}

    public function __invoke(Request $request): View|RedirectResponse
    {
        $karyawan = $request->user()->karyawan()
            ->with(['unitKerja:id,nama_unit'])
            ->first();

        if (! $karyawan) {
            return redirect()->route('me.profile')
                ->with('error', 'Akun Anda belum terhubung dengan data karyawan.');
        }

        $validated = $request->validate([
            'bulan' => ['nullable', 'integer', 'between:1,12'],
            'tahun' => ['nullable', 'integer', 'between:2000,2100'],
        ]);
        $bulan = (int) ($validated['bulan'] ?? now()->month);
        $tahun = (int) ($validated['tahun'] ?? now()->year);

        return view('absensi.show', [
            'karyawan' => $karyawan,
            'bulan' => $bulan,
            'tahun' => $tahun,
            'personalMode' => true,
            ...$this->absensiService->kalender($karyawan, $bulan, $tahun),
        ]);
    }
}
