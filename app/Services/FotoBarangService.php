<?php

namespace App\Services;

use App\Models\Barang;
use App\Models\FotoBarang;
use App\Models\StoredFile;
use App\Repositories\FotoBarangRepository;
use App\Support\PreparedUpload;

class FotoBarangService
{
    public function __construct(
        private FotoBarangRepository $fotoBarangRepository,
        private TransactionalFileStorage $fileStorage,
        private StoredFileService $storedFiles,
        private AsyncUploadService $asyncUploads,
        private DashboardCache $dashboardCache,
    ) {}

    public function store(Barang $barang, array $data): FotoBarang
    {
        if (! empty($data['foto_upload_uuid'])) {
            return $this->claimUpload($barang, $data['foto_upload_uuid'], 'asset_photo', $data['keterangan'] ?? null);
        }

        $prepared = $this->storedFiles->prepare($data['foto'], 'asset_photo');

        try {
            return $this->storePrepared($barang, $prepared, $data['keterangan'] ?? null);
        } finally {
            $prepared->cleanup();
        }
    }

    public function claimUpload(
        Barang $barang,
        string $uuid,
        string $policy = 'asset_gallery',
        ?string $keterangan = null,
    ): FotoBarang {
        return $this->fileStorage->transaction(function () use ($barang, $uuid, $policy, $keterangan) {
            $token = StoredFile::query()->where('uuid', $uuid)->firstOrFail();
            $foto = $this->fotoBarangRepository->create($barang, [
                'path' => $token->path ?: 'pending/'.$token->uuid,
                'keterangan' => $keterangan,
            ]);
            $claimed = $this->asyncUploads->claim($token, $foto, auth()->user(), $policy, 'foto');
            if ($claimed->isAvailable()) {
                $this->storedFiles->syncLegacyOwner($claimed);
                $foto->refresh();
            }
            $this->dashboardCache->invalidateAfterCommit();

            return $foto;
        });
    }

    public function storePrepared(Barang $barang, PreparedUpload $prepared, ?string $keterangan = null): FotoBarang
    {
        return $this->fileStorage->transaction(function () use ($barang, $prepared, $keterangan) {
            $registry = $this->storedFiles->persist(
                $prepared,
                (int) $barang->koperasi_id,
                'foto_pendukung',
                $barang,
                auth()->id(),
            );

            $foto = $this->fotoBarangRepository->create($barang, [
                'path' => $registry->path,
                'keterangan' => $keterangan,
            ]);
            $this->storedFiles->assignOwner($registry, $foto, 'foto');

            $this->dashboardCache->invalidateAfterCommit();

            return $foto;
        });
    }

    public function destroy(Barang $barang, FotoBarang $foto): void
    {
        $this->fileStorage->transaction(function () use ($foto) {
            $path = $foto->path;
            $this->storedFiles->deleteForOwner($foto);
            $this->fotoBarangRepository->delete($foto);
            $this->fileStorage->deleteAfterCommit('public', $path);
            $this->dashboardCache->invalidateAfterCommit();
        });
    }
}
