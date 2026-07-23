<?php

namespace App\Services;

use App\Models\Barang;
use App\Models\FotoBarang;
use App\Repositories\FotoBarangRepository;

class FotoBarangService
{
    public function __construct(
        private FotoBarangRepository $fotoBarangRepository,
        private TransactionalFileStorage $fileStorage,
        private DashboardCache $dashboardCache,
    ) {}

    public function store(Barang $barang, array $data): FotoBarang
    {
        return $this->fileStorage->transaction(function () use ($barang, $data) {
            $path = $this->fileStorage->store('public', 'barang-foto', $data['foto']);

            $foto = $this->fotoBarangRepository->create($barang, [
                'path' => $path,
                'keterangan' => $data['keterangan'] ?? null,
            ]);

            $this->dashboardCache->invalidateAfterCommit();

            return $foto;
        });
    }

    public function destroy(Barang $barang, FotoBarang $foto): void
    {
        $this->fileStorage->transaction(function () use ($foto) {
            $path = $foto->path;
            $this->fotoBarangRepository->delete($foto);
            $this->fileStorage->deleteAfterCommit('public', $path);
            $this->dashboardCache->invalidateAfterCommit();
        });
    }
}
