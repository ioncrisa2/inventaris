<?php

namespace App\Services;

use App\Models\Barang;
use App\Models\DokumenBarang;
use App\Models\StoredFile;
use App\Repositories\DokumenBarangRepository;
use App\Support\PreparedUpload;

class DokumenBarangService
{
    public function __construct(
        private DokumenBarangRepository $dokumenBarangRepository,
        private TransactionalFileStorage $fileStorage,
        private StoredFileService $storedFiles,
        private AsyncUploadService $asyncUploads,
        private DashboardCache $dashboardCache,
    ) {}

    public function store(Barang $barang, array $data): DokumenBarang
    {
        if (! empty($data['dokumen_upload_uuid'])) {
            return $this->claimUpload($barang, $data['dokumen_upload_uuid'], $data['jenis_dokumen']);
        }

        $prepared = $this->storedFiles->prepare($data['dokumen'], 'business_documents');

        try {
            return $this->storePrepared($barang, $prepared, $data['jenis_dokumen']);
        } finally {
            $prepared->cleanup();
        }
    }

    public function claimUpload(Barang $barang, string $uuid, string $jenisDokumen): DokumenBarang
    {
        return $this->fileStorage->transaction(function () use ($barang, $uuid, $jenisDokumen) {
            $token = StoredFile::query()->where('uuid', $uuid)->firstOrFail();
            $dokumen = $this->dokumenBarangRepository->create($barang, [
                'jenis_dokumen' => $jenisDokumen,
                'nama_asli' => $token->original_name,
                'path' => $token->path ?: 'pending/'.$token->uuid,
            ]);
            $claimed = $this->asyncUploads->claim(
                $token,
                $dokumen,
                auth()->user(),
                'business_documents',
                'dokumen',
            );
            if ($claimed->isAvailable()) {
                $this->storedFiles->syncLegacyOwner($claimed);
                $dokumen->refresh();
            }
            $this->dashboardCache->invalidateAfterCommit();

            return $dokumen;
        });
    }

    public function storePrepared(Barang $barang, PreparedUpload $prepared, string $jenisDokumen): DokumenBarang
    {
        return $this->fileStorage->transaction(function () use ($barang, $prepared, $jenisDokumen) {
            $registry = $this->storedFiles->persist(
                $prepared,
                (int) $barang->koperasi_id,
                'dokumen',
                $barang,
                auth()->id(),
            );

            $dokumen = $this->dokumenBarangRepository->create($barang, [
                'jenis_dokumen' => $jenisDokumen,
                'nama_asli' => $prepared->originalName,
                'path' => $registry->path,
            ]);
            $this->storedFiles->assignOwner($registry, $dokumen, 'dokumen');

            $this->dashboardCache->invalidateAfterCommit();

            return $dokumen;
        });
    }

    public function destroy(Barang $barang, DokumenBarang $dokumen): void
    {
        $this->fileStorage->transaction(function () use ($dokumen) {
            $path = $dokumen->path;
            $this->storedFiles->deleteForOwner($dokumen);
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
        return $this->storedFiles->privateResponse(
            $dokumen,
            'local',
            $dokumen->path,
            $dokumen->nama_asli,
            'dokumen',
        );
    }
}
