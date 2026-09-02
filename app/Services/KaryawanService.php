<?php

namespace App\Services;

use App\Models\Karyawan;
use App\Models\StoredFile;
use App\Models\UnitKerja;
use App\Repositories\KaryawanRepository;
use App\Support\PerPage;
use App\Support\PreparedUpload;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class KaryawanService
{
    public function __construct(
        private KaryawanRepository $karyawanRepository,
        private DokumenKaryawanService $dokumenKaryawanService,
        private TransactionalFileStorage $fileStorage,
        private StoredFileService $storedFiles,
        private AsyncUploadService $asyncUploads,
        private DashboardCache $dashboardCache,
    ) {}

    /**
     * @param  array{search?: ?string, koperasi_id?: ?int, unit_kerja_id?: ?string, status_karyawan?: ?string, kelengkapan?: ?string}  $filters
     */
    public function list(array $filters, int $perPage = PerPage::DEFAULT): LengthAwarePaginator
    {
        return $this->karyawanRepository->paginate($filters, $perPage);
    }

    /**
     * Simpan karyawan baru sekaligus dokumen pendukung dari repeater (baris
     * kosong yang tidak diisi file diabaikan), dalam satu transaction.
     */
    public function store(array $data): Karyawan
    {
        $this->ensureRelasiTenantValid($data);
        $foto = null;
        $dokumen = [];

        try {
            $foto = ($data['foto_karyawan'] ?? null) instanceof UploadedFile
                ? $this->storedFiles->prepare($data['foto_karyawan'], 'employee_photo')
                : null;
            $dokumen = $this->prepareDokumen($data);
            $dokumenToken = $this->dokumenToken($data);

            return DB::transaction(function () use ($data, $foto, $dokumen, $dokumenToken) {
                $fotoToken = $data['foto_karyawan_upload_uuid'] ?? null;
                unset($data['dokumen'], $data['foto_karyawan'], $data['foto_karyawan_upload_uuid']);
                $karyawan = $this->karyawanRepository->create($data);

                if ($foto) {
                    $registry = $this->storedFiles->persist(
                        $foto,
                        (int) $karyawan->koperasi_id,
                        'foto_karyawan',
                        $karyawan,
                        auth()->id(),
                    );
                    $karyawan = $this->karyawanRepository->update($karyawan, ['foto_karyawan' => $registry->path]);
                } elseif ($fotoToken) {
                    $token = StoredFile::query()->where('uuid', $fotoToken)->firstOrFail();
                    $claimed = $this->asyncUploads->claim(
                        $token,
                        $karyawan,
                        auth()->user(),
                        'employee_photo',
                        'foto_karyawan',
                    );
                    if ($claimed->isAvailable()) {
                        $this->storedFiles->syncLegacyOwner($claimed);
                        $karyawan->refresh();
                    }
                }

                foreach ($dokumen as $baris) {
                    $this->dokumenKaryawanService->storePrepared($karyawan, $baris['upload'], $baris['jenis']);
                }
                foreach ($dokumenToken as $baris) {
                    $this->dokumenKaryawanService->claimUpload($karyawan, $baris['uuid'], $baris['jenis']);
                }
                $this->dashboardCache->invalidateAfterCommit();

                return $karyawan;
            });
        } finally {
            $foto?->cleanup();
            foreach ($dokumen as $baris) {
                $baris['upload']->cleanup();
            }
        }
    }

    /**
     * Baris repeater dokumen yang benar-benar diisi file (baris kosong yang
     * tersisa karena tombol tambah/hapus tidak dipakai, diabaikan saja).
     */
    private function dokumenTerisi(array $data): array
    {
        return array_filter(
            $data['dokumen'] ?? [],
            fn ($baris) => (isset($baris['dokumen']) && $baris['dokumen'] instanceof UploadedFile)
                || filled($baris['dokumen_upload_uuid'] ?? null)
        );
    }

    /** @return list<array{jenis:string,upload:PreparedUpload}> */
    private function prepareDokumen(array $data): array
    {
        $prepared = [];

        try {
            foreach ($this->dokumenTerisi($data) as $baris) {
                if (! isset($baris['dokumen']) || ! $baris['dokumen'] instanceof UploadedFile) {
                    continue;
                }
                $prepared[] = [
                    'jenis' => $baris['jenis_dokumen'],
                    'upload' => $this->storedFiles->prepare($baris['dokumen'], 'business_documents'),
                ];
            }
        } catch (\Throwable $exception) {
            foreach ($prepared as $baris) {
                $baris['upload']->cleanup();
            }

            throw $exception;
        }

        return $prepared;
    }

    /** @return list<array{jenis:string,uuid:string}> */
    private function dokumenToken(array $data): array
    {
        return array_values(array_map(
            fn (array $baris): array => [
                'jenis' => $baris['jenis_dokumen'],
                'uuid' => $baris['dokumen_upload_uuid'],
            ],
            array_filter(
                $this->dokumenTerisi($data),
                fn (array $baris): bool => filled($baris['dokumen_upload_uuid'] ?? null),
            ),
        ));
    }

    private function simpanDokumen(Karyawan $karyawan, array $dokumenBaris): void
    {
        foreach ($dokumenBaris as $baris) {
            $this->dokumenKaryawanService->store($karyawan, [
                'jenis_dokumen' => $baris['jenis_dokumen'],
                'dokumen' => $baris['dokumen'],
            ]);
        }
    }

    public function destroy(Karyawan $karyawan): void
    {
        $this->destroyMany([$karyawan->id]);
    }

    public function destroyMany(array $ids): int
    {
        return DB::transaction(function () use ($ids) {
            $ids = array_values(array_unique(array_map('intval', $ids)));
            $karyawans = $this->karyawanRepository->findManyForDelete($ids);

            if ($ids === [] || $karyawans->count() !== count($ids)) {
                throw new \DomainException('Sebagian karyawan sudah tidak tersedia. Muat ulang halaman lalu coba lagi.');
            }

            $karyawans->each(fn (Karyawan $karyawan) => $this->ensureCanDelete($karyawan));

            foreach ($karyawans as $karyawan) {
                $this->storedFiles->deleteForOwner($karyawan);
                $this->fileStorage->deleteAfterCommit('public', $karyawan->foto_karyawan);

                foreach ($karyawan->dokumen as $dokumenRecord) {
                    $this->storedFiles->deleteForOwner($dokumenRecord);
                    $this->fileStorage->deleteAfterCommit('local', $dokumenRecord->path);
                }

                $this->karyawanRepository->delete($karyawan);
            }

            $this->dashboardCache->invalidateAfterCommit();

            return $karyawans->count();
        }, 3);
    }

    public function ensureCanDelete(Karyawan $karyawan): void
    {
        $atribut = $karyawan->getAttributes();
        $memilikiRelasi = array_key_exists('absensis_exists', $atribut)
            ? (bool) ($karyawan->absensis_exists
                || $karyawan->transaksi_gaji_exists
                || $karyawan->bawahan_langsung_exists
                || $karyawan->riwayat_perubahan_exists)
            : ($karyawan->absensis()->exists()
                || $karyawan->transaksiGaji()->exists()
                || $karyawan->bawahanLangsung()->exists()
                || $karyawan->riwayatPerubahan()->exists());

        if ($memilikiRelasi) {
            throw new \DomainException('Karyawan tidak dapat dihapus karena masih terhubung dengan absensi, transaksi gaji, histori perubahan, atau tercatat sebagai atasan. Hapus atau pindahkan relasi tersebut terlebih dahulu.');
        }
    }

    /**
     * Kategorikan usia karyawan untuk ditampilkan di halaman detail.
     */
    public function kategoriUsia(Karyawan $karyawan): string
    {
        $usia = $karyawan->tanggal_lahir->age;

        if ($usia < 25) {
            return 'Usia Muda (< 25 tahun)';
        }

        if ($usia >= 25 && $usia <= 50) {
            return 'Usia Produktif (25-50 tahun)';
        }

        return 'Mendekati Pensiun (> 50 tahun)';
    }

    /**
     * Masa kerja karyawan dari tanggal masuk s.d. tanggal mengundurkan diri
     * (kalau sudah keluar) atau hari ini (kalau masih aktif).
     */
    public function masaKerja(Karyawan $karyawan): string
    {
        if (! $karyawan->tanggal_masuk_kerja) {
            return 'Belum diketahui';
        }

        $mulai = $karyawan->tanggal_masuk_kerja;
        $akhir = $karyawan->tanggal_mengundurkan_diri ?? now();

        $tahun = (int) $mulai->diffInYears($akhir);
        $bulan = (int) $mulai->copy()->addYears($tahun)->diffInMonths($akhir);

        return "{$tahun} tahun {$bulan} bulan";
    }

    private function ensureRelasiTenantValid(array $data): void
    {
        if (! UnitKerja::query()->whereKey((int) $data['unit_kerja_id'])->exists()) {
            throw ValidationException::withMessages([
                'unit_kerja_id' => 'Unit kerja tidak tersedia dalam koperasi Anda.',
            ]);
        }

        $atasanId = $data['atasan_langsung_id'] ?? null;

        if ($atasanId !== null && ! Karyawan::query()->whereKey((int) $atasanId)->exists()) {
            throw ValidationException::withMessages([
                'atasan_langsung_id' => 'Atasan langsung tidak tersedia dalam koperasi Anda.',
            ]);
        }
    }
}
