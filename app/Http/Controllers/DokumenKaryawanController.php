<?php

namespace App\Http\Controllers;

use App\Models\DokumenKaryawan;
use App\Models\Karyawan;
use App\Services\DokumenKaryawanService;

class DokumenKaryawanController extends Controller
{
    public function __construct(private DokumenKaryawanService $dokumenKaryawanService) {}

    public function download(Karyawan $karyawan, DokumenKaryawan $dokumenKaryawan)
    {
        $this->authorize('view', $karyawan);
        abort_unless($dokumenKaryawan->karyawan_id === $karyawan->id, 404);

        return $this->dokumenKaryawanService->streamedDownload($dokumenKaryawan);
    }
}
