<?php

namespace App\Http\Controllers;

use App\Http\Requests\Karyawan\StoreRiwayatKaryawanRequest;
use App\Models\DokumenRiwayatKaryawan;
use App\Models\Karyawan;
use App\Models\RiwayatKaryawan;
use App\Services\RiwayatKaryawanService;
use Illuminate\Http\Request;

class RiwayatKaryawanController extends Controller
{
    public function __construct(
        private RiwayatKaryawanService $riwayatKaryawanService,
    ) {}

    public function store(StoreRiwayatKaryawanRequest $request, Karyawan $karyawan)
    {
        $this->riwayatKaryawanService->catat(
            $karyawan,
            $request->user(),
            $request->validated(),
        );

        return redirect()
            ->route('karyawan.show', $karyawan)
            ->with('success', 'Perubahan karyawan berhasil diterapkan dan dicatat dalam histori.');
    }

    public function download(
        Request $request,
        Karyawan $karyawan,
        RiwayatKaryawan $riwayatKaryawan,
        DokumenRiwayatKaryawan $dokumenRiwayatKaryawan,
    ) {
        $this->authorize('view', $karyawan);
        abort_unless($request->user()->can('karyawan.riwayat.view'), 403);
        abort_unless($riwayatKaryawan->karyawan_id === $karyawan->id, 404);
        abort_unless($dokumenRiwayatKaryawan->riwayat_karyawan_id === $riwayatKaryawan->id, 404);

        return $this->riwayatKaryawanService->streamedDownload($dokumenRiwayatKaryawan);
    }
}
