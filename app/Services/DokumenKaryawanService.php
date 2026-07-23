<?php

namespace App\Services;

use App\Models\DokumenKaryawan;
use App\Models\Karyawan;
use App\Repositories\DokumenKaryawanRepository;
use Illuminate\Support\Facades\Storage;

class DokumenKaryawanService
{
    public function __construct(
        private DokumenKaryawanRepository $dokumenKaryawanRepository,
        private TransactionalFileStorage $fileStorage,
        private DashboardCache $dashboardCache,
    ) {}

    public function store(Karyawan $karyawan, array $data): DokumenKaryawan
    {
        return $this->fileStorage->transaction(function () use ($karyawan, $data) {
            $file = $data['dokumen'];
            $path = $this->fileStorage->store('local', 'dokumen-karyawan', $file);

            $dokumen = $this->dokumenKaryawanRepository->create($karyawan, [
                'jenis_dokumen' => $data['jenis_dokumen'],
                'nama_asli' => $file->getClientOriginalName(),
                'path' => $path,
            ]);

            $this->dashboardCache->invalidateAfterCommit();

            return $dokumen;
        });
    }

    public function destroy(Karyawan $karyawan, DokumenKaryawan $dokumen): void
    {
        $this->fileStorage->transaction(function () use ($dokumen) {
            $path = $dokumen->path;
            $this->dokumenKaryawanRepository->delete($dokumen);
            $this->fileStorage->deleteAfterCommit('local', $path);
            $this->dashboardCache->invalidateAfterCommit();
        });
    }

    /**
     * Tampilkan dokumen inline (bukan paksa unduh) supaya ijazah/sertifikat
     * bisa langsung dilihat di tab baru.
     */
    public function streamedDownload(DokumenKaryawan $dokumen)
    {
        return Storage::disk('local')->response($dokumen->path, $dokumen->nama_asli);
    }
}
