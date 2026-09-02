<?php

namespace App\Services;

use App\Models\Barang;
use App\Models\StoredFile;
use App\Models\UnitKerja;
use App\Repositories\BarangRepository;
use App\Repositories\RiwayatKondisiBarangRepository;
use App\Support\PerPage;
use App\Support\PreparedUpload;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Picqer\Barcode\BarcodeGeneratorSVG;

class BarangService
{
    public function __construct(
        private BarangRepository $barangRepository,
        private RiwayatKondisiBarangRepository $riwayatKondisiBarangRepository,
        private KodeBarangGenerator $kodeBarangGenerator,
        private FotoBarangService $fotoBarangService,
        private DokumenBarangService $dokumenBarangService,
        private TransactionalFileStorage $fileStorage,
        private StoredFileService $storedFiles,
        private AsyncUploadService $asyncUploads,
        private DashboardCache $dashboardCache,
    ) {}

    /**
     * @param  array{search?: ?string, koperasi_id?: ?int, unit_kerja_id?: ?string, kategori?: ?string, kondisi?: ?string, kelengkapan?: ?string}  $filters
     */
    public function list(array $filters, int $perPage = PerPage::DEFAULT): LengthAwarePaginator
    {
        return $this->barangRepository->paginate($filters, $perPage);
    }

    /**
     * Simpan barang baru sekaligus catatan kondisi awalnya (satu transaction),
     * dengan kondisi awal ditentukan otomatis dari besar harga perolehannya.
     */
    public function store(array $data): Barang
    {
        $this->ensureUnitKerjaTersedia((int) $data['unit_kerja_id']);
        $fotoSampul = null;
        $fotoPendukung = [];
        $dokumen = [];

        try {
            $fotoSampul = ($data['foto_sampul'] ?? null) instanceof UploadedFile
                ? $this->storedFiles->prepare($data['foto_sampul'], 'asset_photo')
                : null;
            $fotoPendukung = $this->storedFiles->prepareMany(
                array_values(array_filter($data['foto_pendukung'] ?? [], fn ($foto) => $foto instanceof UploadedFile)),
                'asset_gallery',
            );
            $dokumen = $this->prepareDokumen($data);
            $dokumenToken = $this->dokumenToken($data);

            return DB::transaction(function () use ($data, $fotoSampul, $fotoPendukung, $dokumen, $dokumenToken) {
                $kondisiAwal = $this->kondisiAwal((float) $data['harga_perolehan']);
                $data['kode_barang'] = $this->kodeBarangGenerator->generate(
                    $data['kategori'],
                    (int) $data['unit_kerja_id'],
                    $data['tanggal_perolehan'],
                );
                $fotoSampulToken = $data['foto_sampul_upload_uuid'] ?? null;
                $fotoPendukungToken = array_values($data['foto_pendukung_upload_uuids'] ?? []);
                unset(
                    $data['foto_sampul'],
                    $data['foto_sampul_upload_uuid'],
                    $data['foto_pendukung'],
                    $data['foto_pendukung_upload_uuids'],
                    $data['dokumen'],
                );

                $barang = $this->barangRepository->create($data);
                if ($fotoSampul) {
                    $registry = $this->storedFiles->persist(
                        $fotoSampul,
                        (int) $barang->koperasi_id,
                        'foto_sampul',
                        $barang,
                        auth()->id(),
                    );
                    $barang = $this->barangRepository->update($barang, ['foto_sampul' => $registry->path]);
                } elseif ($fotoSampulToken) {
                    $token = StoredFile::query()->where('uuid', $fotoSampulToken)->firstOrFail();
                    $claimed = $this->asyncUploads->claim($token, $barang, auth()->user(), 'asset_photo', 'foto_sampul');
                    if ($claimed->isAvailable()) {
                        $this->storedFiles->syncLegacyOwner($claimed);
                        $barang->refresh();
                    }
                }

                $this->riwayatKondisiBarangRepository->create($barang, [
                    'tanggal_pemeriksaan' => now()->toDateString(),
                    'kondisi' => $kondisiAwal,
                    'keterangan' => "Kondisi awal saat barang ditambahkan (otomatis: {$kondisiAwal}).",
                    'biaya_perbaikan' => null,
                ]);

                foreach ($fotoPendukung as $foto) {
                    $this->fotoBarangService->storePrepared($barang, $foto);
                }
                foreach ($fotoPendukungToken as $uuid) {
                    $this->fotoBarangService->claimUpload($barang, $uuid, 'asset_gallery');
                }
                foreach ($dokumen as $baris) {
                    $this->dokumenBarangService->storePrepared($barang, $baris['upload'], $baris['jenis']);
                }
                foreach ($dokumenToken as $baris) {
                    $this->dokumenBarangService->claimUpload($barang, $baris['uuid'], $baris['jenis']);
                }

                $this->dashboardCache->invalidateAfterCommit();

                return $barang;
            }, 3);
        } finally {
            $fotoSampul?->cleanup();
            foreach ($fotoPendukung as $foto) {
                $foto->cleanup();
            }
            foreach ($dokumen as $baris) {
                $baris['upload']->cleanup();
            }
        }
    }

    public function update(Barang $barang, array $data): Barang
    {
        $this->ensureUnitKerjaTersedia((int) $data['unit_kerja_id'], $barang->koperasi_id);
        $fotoSampul = null;
        $dokumen = [];
        $dokumenToken = [];

        try {
            $fotoSampul = ($data['foto_sampul'] ?? null) instanceof UploadedFile
                ? $this->storedFiles->prepare($data['foto_sampul'], 'asset_photo')
                : null;
            $dokumen = $this->prepareDokumen($data);
            $dokumenToken = $this->dokumenToken($data);

            return DB::transaction(function () use ($barang, $data, $fotoSampul, $dokumen, $dokumenToken) {
                $fotoLama = null;
                $fotoSampulToken = $data['foto_sampul_upload_uuid'] ?? null;
                unset($data['foto_sampul'], $data['foto_sampul_upload_uuid'], $data['dokumen']);

                if ($fotoSampul || $fotoSampulToken) {
                    $fotoLama = $barang->foto_sampul;
                    $this->storedFiles->deleteForOwner($barang, 'foto_sampul', 'replace');
                }
                if ($fotoSampul) {
                    $registry = $this->storedFiles->persist(
                        $fotoSampul,
                        (int) $barang->koperasi_id,
                        'foto_sampul',
                        $barang,
                        auth()->id(),
                    );
                    $data['foto_sampul'] = $registry->path;
                } elseif ($fotoSampulToken) {
                    $token = StoredFile::query()->where('uuid', $fotoSampulToken)->firstOrFail();
                    $claimed = $this->asyncUploads->claim($token, $barang, auth()->user(), 'asset_photo', 'foto_sampul');
                    $data['foto_sampul'] = $claimed->isAvailable() ? $claimed->path : null;
                }

                $barang = $this->barangRepository->update($barang, $data);
                foreach ($dokumen as $baris) {
                    $this->dokumenBarangService->storePrepared($barang, $baris['upload'], $baris['jenis']);
                }
                foreach ($dokumenToken as $baris) {
                    $this->dokumenBarangService->claimUpload($barang, $baris['uuid'], $baris['jenis']);
                }
                $this->fileStorage->deleteAfterCommit('public', $fotoLama);
                $this->dashboardCache->invalidateAfterCommit();

                return $barang;
            }, 3);
        } finally {
            $fotoSampul?->cleanup();
            foreach ($dokumen as $baris) {
                $baris['upload']->cleanup();
            }
        }
    }

    public function destroy(Barang $barang): void
    {
        $this->destroyMany([$barang->id]);
    }

    public function destroyMany(array $ids): int
    {
        return DB::transaction(function () use ($ids) {
            $ids = array_values(array_unique(array_map('intval', $ids)));
            $barangs = $this->barangRepository->findManyForDelete($ids);
            $this->pastikanSemuaDitemukan($barangs->count(), count($ids));
            $barangs->each(fn (Barang $barang) => $this->ensureCanDelete($barang));

            foreach ($barangs as $barang) {
                $this->storedFiles->deleteForOwner($barang);
                $this->barangRepository->delete($barang);
            }

            $this->dashboardCache->invalidateAfterCommit();

            return $barangs->count();
        }, 3);
    }

    private function pastikanSemuaDitemukan(int $jumlahDitemukan, int $jumlahDiminta): void
    {
        if ($jumlahDiminta === 0 || $jumlahDitemukan !== $jumlahDiminta) {
            throw new \DomainException('Sebagian barang sudah tidak tersedia. Muat ulang halaman lalu coba lagi.');
        }
    }

    public function ensureCanDelete(Barang $barang): void
    {
        $atribut = $barang->getAttributes();
        $memilikiRelasi = array_key_exists('riwayat_kondisi_exists', $atribut)
            ? (bool) ($barang->riwayat_kondisi_exists || $barang->foto_pendukung_exists || $barang->dokumen_exists)
            : ($barang->riwayatKondisi()->exists() || $barang->fotoPendukung()->exists() || $barang->dokumen()->exists());

        if ($memilikiRelasi) {
            throw new \DomainException('Barang tidak dapat dihapus karena masih memiliki riwayat kondisi, foto pendukung, atau dokumen. Hapus relasi tersebut terlebih dahulu.');
        }
    }

    /**
     * QR code yang meng-encode link ke halaman detail barang, di-generate
     * on-the-fly (tidak disimpan sebagai file) supaya selalu sinkron kalau
     * URL berubah.
     */
    public function qrCodeSvg(Barang $barang): string
    {
        $builder = new Builder(writer: new SvgWriter);

        return $builder->build(data: route('barang.show', $barang))->getString();
    }

    /**
     * Barcode Code128 klasik yang meng-encode kode_barang itu sendiri, untuk
     * alur kerja scanner-gun.
     */
    public function barcodeCode128Svg(Barang $barang): string
    {
        $generator = new BarcodeGeneratorSVG;

        return $generator->getBarcode($barang->kode_barang, $generator::TYPE_CODE_128);
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

    private function simpanDokumen(Barang $barang, array $dokumenBaris): void
    {
        foreach ($dokumenBaris as $baris) {
            $this->dokumenBarangService->store($barang, [
                'jenis_dokumen' => $baris['jenis_dokumen'],
                'dokumen' => $baris['dokumen'],
            ]);
        }
    }

    /**
     * Contoh variabel, tipe data, dan percabangan if-elseif-else: kondisi awal
     * barang ditentukan otomatis dari besar harga perolehannya.
     */
    private function kondisiAwal(float $hargaPerolehan): string
    {
        $batasHargaBaru = 10000000; // Rp 10 juta (tipe data int)

        if ($hargaPerolehan >= $batasHargaBaru) {
            return 'Baru';
        }

        if ($hargaPerolehan > 0) {
            return 'Baik';
        }

        return 'Cukup Baik';
    }

    private function ensureUnitKerjaTersedia(int $unitKerjaId, ?int $koperasiId = null): void
    {
        $unitKerja = UnitKerja::query()->find($unitKerjaId);
        $valid = $unitKerja !== null
            && ($koperasiId === null || (int) $unitKerja->koperasi_id === (int) $koperasiId);

        if (! $valid) {
            throw ValidationException::withMessages([
                'unit_kerja_id' => 'Unit kerja tidak tersedia dalam koperasi Anda.',
            ]);
        }
    }
}
