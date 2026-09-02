<?php

namespace App\Http\Controllers;

use App\Http\Requests\Karyawan\StoreRiwayatKaryawanRequest;
use App\Models\DokumenRiwayatKaryawan;
use App\Models\Karyawan;
use App\Models\RiwayatKaryawan;
use App\Repositories\KaryawanRepository;
use App\Repositories\UnitKerjaRepository;
use App\Services\RiwayatKaryawanService;
use App\Support\KaryawanPerubahanSchema;
use Illuminate\Http\Request;

class RiwayatKaryawanController extends Controller
{
    public function __construct(
        private RiwayatKaryawanService $riwayatKaryawanService,
        private UnitKerjaRepository $unitKerjaRepository,
        private KaryawanRepository $karyawanRepository,
    ) {}

    public function create(Request $request, Karyawan $karyawan)
    {
        $this->authorize('view', $karyawan);

        $jenisPerubahanTersedia = KaryawanPerubahanSchema::allowedTypesFor($request->user());
        abort_if($jenisPerubahanTersedia === [], 403);

        $unitKerjas = isset($jenisPerubahanTersedia['mutasi_promosi'])
            ? $this->unitKerjaRepository->orderedList()
            : collect();
        $atasanOptions = isset($jenisPerubahanTersedia['mutasi_promosi'])
            ? $this->karyawanRepository->orderedList()->reject(fn ($item) => $item->id === $karyawan->id)
            : collect();

        return view('karyawan.edit', compact(
            'karyawan',
            'jenisPerubahanTersedia',
            'unitKerjas',
            'atasanOptions',
        ));
    }

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
