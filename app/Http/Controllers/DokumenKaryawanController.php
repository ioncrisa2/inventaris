<?php

namespace App\Http\Controllers;

use App\Http\Requests\DokumenKaryawan\StoreDokumenKaryawanRequest;
use App\Models\DokumenKaryawan;
use App\Models\Karyawan;
use App\Services\DokumenKaryawanService;

class DokumenKaryawanController extends Controller
{
    public function __construct(private DokumenKaryawanService $dokumenKaryawanService) {}

    public function store(StoreDokumenKaryawanRequest $request, Karyawan $karyawan)
    {
        $this->dokumenKaryawanService->store($karyawan, $request->validated());

        return redirect()->route('karyawan.show', $karyawan)->with('success', 'Dokumen berhasil diunggah.');
    }

    public function download(Karyawan $karyawan, DokumenKaryawan $dokumenKaryawan)
    {
        $this->authorize('view', $karyawan);
        abort_unless($dokumenKaryawan->karyawan_id === $karyawan->id, 404);

        return $this->dokumenKaryawanService->streamedDownload($dokumenKaryawan);
    }

    public function destroy(Karyawan $karyawan, DokumenKaryawan $dokumenKaryawan)
    {
        $this->authorize('update', $karyawan);
        abort_unless($dokumenKaryawan->karyawan_id === $karyawan->id, 404);

        $this->dokumenKaryawanService->destroy($karyawan, $dokumenKaryawan);

        return redirect()->route('karyawan.show', $karyawan)->with('success', 'Dokumen berhasil dihapus.');
    }
}
