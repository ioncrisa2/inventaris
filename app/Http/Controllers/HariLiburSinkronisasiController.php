<?php

namespace App\Http\Controllers;

use App\Http\Requests\HariLibur\BandingkanHariLiburRequest;
use App\Http\Requests\HariLibur\SinkronisasiHariLiburRequest;
use App\Services\HariLiburSinkronisasiService;

class HariLiburSinkronisasiController extends Controller
{
    public function __construct(private HariLiburSinkronisasiService $hariLiburSinkronisasiService) {}

    public function create(BandingkanHariLiburRequest $request)
    {
        $tahun = $request->validated('tahun');
        $tahun = $tahun !== null ? (int) $tahun : null;
        $hasil = null;
        $errorPesan = null;

        if ($tahun !== null) {
            try {
                $hasil = $this->hariLiburSinkronisasiService->bandingkan($tahun);
            } catch (\RuntimeException $exception) {
                $errorPesan = $exception->getMessage();
            }
        }

        return view('hari-libur.sinkronisasi', compact(
            'tahun',
            'hasil',
            'errorPesan',
        ));
    }

    public function store(SinkronisasiHariLiburRequest $request)
    {
        $tahun = (int) $request->validated('tahun');

        try {
            $jumlah = $this->hariLiburSinkronisasiService->terapkan(
                $request->user(),
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

        return redirect()->route('hari-libur.tahun', ['tahun' => $tahun])->with('success', $pesan);
    }
}
