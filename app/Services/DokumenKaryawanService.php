<?php

namespace App\Services;

use App\Models\DokumenKaryawan;
use App\Models\Karyawan;
use App\Models\StoredFile;
use App\Repositories\DokumenKaryawanRepository;
use App\Support\PreparedUpload;

class DokumenKaryawanService
{
    public function __construct(
        private DokumenKaryawanRepository $dokumenKaryawanRepository,
        private TransactionalFileStorage $fileStorage,
        private StoredFileService $storedFiles,
        private AsyncUploadService $asyncUploads,
        private DashboardCache $dashboardCache,
    ) {}

    public function store(Karyawan $karyawan, array $data): DokumenKaryawan
    {
        if (! empty($data['dokumen_upload_uuid'])) {
            return $this->claimUpload($karyawan, $data['dokumen_upload_uuid'], $data['jenis_dokumen']);
        }

        $prepared = $this->storedFiles->prepare($data['dokumen'], 'business_documents');

        try {
            return $this->storePrepared($karyawan, $prepared, $data['jenis_dokumen']);
        } finally {
            $prepared->cleanup();
        }
    }

    public function claimUpload(Karyawan $karyawan, string $uuid, string $jenisDokumen): DokumenKaryawan
    {
        return $this->fileStorage->transaction(function () use ($karyawan, $uuid, $jenisDokumen) {
            $token = StoredFile::query()->where('uuid', $uuid)->firstOrFail();
            $dokumen = $this->dokumenKaryawanRepository->create($karyawan, [
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

    public function storePrepared(Karyawan $karyawan, PreparedUpload $prepared, string $jenisDokumen): DokumenKaryawan
    {
        return $this->fileStorage->transaction(function () use ($karyawan, $prepared, $jenisDokumen) {
            $registry = $this->storedFiles->persist(
                $prepared,
                (int) $karyawan->koperasi_id,
                'dokumen',
                $karyawan,
                auth()->id(),
            );

            $dokumen = $this->dokumenKaryawanRepository->create($karyawan, [
                'jenis_dokumen' => $jenisDokumen,
                'nama_asli' => $prepared->originalName,
                'path' => $registry->path,
            ]);
            $this->storedFiles->assignOwner($registry, $dokumen, 'dokumen');

            $this->dashboardCache->invalidateAfterCommit();

            return $dokumen;
        });
    }

    public function destroy(Karyawan $karyawan, DokumenKaryawan $dokumen): void
    {
        $this->fileStorage->transaction(function () use ($dokumen) {
            $path = $dokumen->path;
            $this->storedFiles->deleteForOwner($dokumen);
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
        return $this->storedFiles->privateResponse(
            $dokumen,
            'local',
            $dokumen->path,
            $dokumen->nama_asli,
            'dokumen',
        );
    }
}
