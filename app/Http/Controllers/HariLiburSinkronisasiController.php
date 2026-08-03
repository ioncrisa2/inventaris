<?php

namespace App\Http\Controllers;

use App\Http\Requests\HariLibur\BandingkanHariLiburRequest;
use App\Http\Requests\HariLibur\SinkronisasiHariLiburRequest;
use App\Models\Koperasi;
use App\Services\HariLiburSinkronisasiService;

class HariLiburSinkronisasiController extends Controller
{
    public function __construct(private HariLiburSinkronisasiService $hariLiburSinkronisasiService) {}

    public function create(BandingkanHariLiburRequest $request)
    {
        $tahun = $request->validated('tahun');
        $koperasiId = $request->validated('koperasi_id');
        $tahun = $tahun !== null ? (int) $tahun : null;
        $koperasiId = $koperasiId !== null ? (int) $koperasiId : null;
        $koperasis = Koperasi::query()->select(['id', 'nama'])->orderBy('nama')->get();
        $koperasi = $koperasiId !== null ? $koperasis->firstWhere('id', $koperasiId) : null;
        $hasil = null;
        $errorPesan = null;

        if ($tahun !== null && $koperasiId !== null) {
            try {
                $hasil = $this->hariLiburSinkronisasiService->bandingkan($koperasiId, $tahun);
            } catch (\RuntimeException $exception) {
                $errorPesan = $exception->getMessage();
            }
        }

        return view('hari-libur.sinkronisasi', compact(
            'tahun',
            'koperasiId',
            'koperasis',
            'koperasi',
            'hasil',
            'errorPesan',
        ));
    }

    public function store(SinkronisasiHariLiburRequest $request)
    {
        $tahun = (int) $request->validated('tahun');
        $koperasiId = (int) $request->validated('koperasi_id');

        try {
            $jumlah = $this->hariLiburSinkronisasiService->terapkan(
                $request->user(),
                $koperasiId,
                $tahun,
                $request->validated('pilihan') ?? [],
                $request->validated('snapshot'),
            );
        } catch (\RuntimeException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        $pesan = $jumlah > 0
            ? "{$jumlah} hari libur berhasil ditambahkan dari API."
            : 'Tidak ada hari libur baru yang ditambahkan.';

        return redirect()->route('hari-libur.koperasi', [
            'tahun' => $tahun,
            'koperasi' => $koperasiId,
        ])->with('success', $pesan);
    }
}
