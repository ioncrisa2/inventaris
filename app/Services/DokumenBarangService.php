<?php

namespace App\Services;

use App\Models\Barang;
use App\Models\DokumenBarang;
use App\Repositories\DokumenBarangRepository;
use Illuminate\Support\Facades\Storage;

class DokumenBarangService
{
    public function __construct(
        private DokumenBarangRepository $dokumenBarangRepository,
        private TransactionalFileStorage $fileStorage,
        private DashboardCache $dashboardCache,
    ) {}

    public function store(Barang $barang, array $data): DokumenBarang
    {
        return $this->fileStorage->transaction(function () use ($barang, $data) {
            $file = $data['dokumen'];
            $path = $this->fileStorage->store('local', 'dokumen-barang', $file);

            $dokumen = $this->dokumenBarangRepository->create($barang, [
                'jenis_dokumen' => $data['jenis_dokumen'],
                'nama_asli' => $file->getClientOriginalName(),
                'path' => $path,
            ]);

            $this->dashboardCache->invalidateAfterCommit();

            return $dokumen;
        });
    }

    public function destroy(Barang $barang, DokumenBarang $dokumen): void
    {
        $this->fileStorage->transaction(function () use ($dokumen) {
            $path = $dokumen->path;
            $this->dokumenBarangRepository->delete($dokumen);
            $this->fileStorage->deleteAfterCommit('local', $path);
            $this->dashboardCache->invalidateAfterCommit();
        });
    }

    /**
     * Tampilkan dokumen inline (bukan paksa unduh) supaya nota/kartu garansi
     * bisa langsung dilihat di tab baru.
     */
    public function streamedDownload(DokumenBarang $dokumen)
    {
        return Storage::disk('local')->response($dokumen->path, $dokumen->nama_asli);
    }
}
