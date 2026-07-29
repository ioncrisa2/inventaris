<?php

namespace App\Http\Controllers;

use App\Http\Requests\HariLibur\BandingkanHariLiburRequest;
use App\Http\Requests\HariLibur\SinkronisasiHariLiburRequest;
use App\Models\HariLibur;
use App\Services\HariLiburSinkronisasiService;

class HariLiburSinkronisasiController extends Controller
{
    public function __construct(private HariLiburSinkronisasiService $hariLiburSinkronisasiService) {}

    /**
     * Tampilkan form pilih tahun. Kalau tahun sudah diisi, sekalian
     * tampilkan hasil perbandingan dengan API Nager.Date.
     */
    public function create(BandingkanHariLiburRequest $request)
    {
        $this->authorize('create', HariLibur::class);

        $tahun = $request->validated('tahun') ? (int) $request->validated('tahun') : null;
        $hasil = null;
        $errorPesan = null;

        if ($tahun !== null) {
            try {
                $hasil = $this->hariLiburSinkronisasiService->bandingkan($tahun);
            } catch (\RuntimeException $e) {
                $errorPesan = $e->getMessage();
            }
        }

        return view('hari-libur.sinkronisasi', compact('tahun', 'hasil', 'errorPesan'));
    }

    /**
     * Terapkan tanggal yang dipilih user dari hasil perbandingan.
     */
    public function store(SinkronisasiHariLiburRequest $request)
    {
        $this->authorize('create', HariLibur::class);

        $tahun = (int) $request->validated('tahun');

        try {
            $jumlah = $this->hariLiburSinkronisasiService->terapkan(
                $tahun,
                $request->validated('pilihan') ?? [],
            );
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $pesan = $jumlah > 0
            ? "{$jumlah} hari libur berhasil ditambahkan dari API."
            : 'Tidak ada hari libur baru yang ditambahkan.';

        return redirect()->route('hari-libur.tahun', ['tahun' => $tahun])->with('success', $pesan);
    }
}
